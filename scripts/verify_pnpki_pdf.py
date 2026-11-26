import argparse
import hashlib
import json
import sys
from pathlib import Path

from asn1crypto import pem, x509
from pyhanko.pdf_utils.reader import PdfFileReader
from pyhanko.sign.validation import validate_pdf_signature
from pyhanko.sign.validation.status import SignatureCoverageLevel
from pyhanko_certvalidator import ValidationContext


def load_certificate(file_path):
    data = Path(file_path).read_bytes()

    if pem.detect(data):
        _, _, data = pem.unarmor(data)

    return x509.Certificate.load(data)


def load_certificates(directory):
    path = Path(directory)

    if not path.is_dir():
        return []

    certificates = []

    for cert_file in path.iterdir():
        if cert_file.suffix.lower() in (".cer", ".crt", ".pem"):
            certificates.append(load_certificate(cert_file))

    return certificates


def fingerprint(certificate):
    return hashlib.sha256(certificate.dump()).hexdigest().upper()


def fail(message, signature_count=0, signatures=None):
    print(json.dumps({
        "valid": False,
        "message": message,
        "signature_count": signature_count,
        "signatures": signatures or [],
    }))


def main():
    parser = argparse.ArgumentParser()

    parser.add_argument("--pdf", required=True)
    parser.add_argument("--expected-fingerprint", required=True)
    parser.add_argument("--registered-cert", required=True)
    parser.add_argument("--trust-dir", required=True)
    parser.add_argument("--cert-dir", required=True)

    args = parser.parse_args()

    expected_fingerprint = (
        args.expected_fingerprint
        .replace(":", "")
        .replace(" ", "")
        .upper()
    )

    if len(expected_fingerprint) != 64:
        raise ValueError("Invalid expected certificate fingerprint.")

    registered_certificate = load_certificate(args.registered_cert)
    registered_fingerprint = fingerprint(registered_certificate)

    if registered_fingerprint != expected_fingerprint:
        raise ValueError(
            "The registered certificate does not match its stored fingerprint."
        )

    trust_roots = load_certificates(args.trust_dir)
    other_certs = load_certificates(args.cert_dir)

    if not trust_roots:
        raise RuntimeError("No PNPKI trust root certificates configured.")

    with open(args.pdf, "rb") as pdf_file:
        reader = PdfFileReader(pdf_file)
        signatures = reader.embedded_signatures

        if not signatures:
            fail("The PDF contains no embedded digital signatures.")
            return 0

        if len(signatures) != 1:
            fail(
                "The PDF must contain exactly one Chairperson signature.",
                len(signatures),
            )
            return 0

        signature = signatures[0]

        context = ValidationContext(
            trust_roots=trust_roots,
            other_certs=other_certs,
            allow_fetching=True,
            revocation_mode="require",
            retroactive_revinfo=False,
        )

        status = validate_pdf_signature(
            signature,
            signer_validation_context=context,
        )

        signer_fingerprint = fingerprint(status.signing_cert)

        authorized_signer = (
            signer_fingerprint == registered_fingerprint
        )

        full_file_coverage = (
            status.coverage == SignatureCoverageLevel.ENTIRE_FILE
        )

        valid = (
            status.intact is True
            and status.valid is True
            and status.bottom_line is True
            and status.trust_problem_indic is None
            and authorized_signer
            and full_file_coverage
        )

        result = {
            "field_name": signature.field_name,
            "fingerprint": signer_fingerprint,
            "authorized_signer": authorized_signer,
            "intact": status.intact,
            "cryptographically_valid": status.valid,
            "trusted": status.trust_problem_indic is None,
            "bottom_line": status.bottom_line,
            "full_file_coverage": full_file_coverage,
            "trust_problem": (
                str(status.trust_problem_indic)
                if status.trust_problem_indic is not None
                else None
            ),
            "modification_level": (
                str(status.modification_level)
                if status.modification_level is not None
                else None
            ),
            "docmdp_ok": status.docmdp_ok,
        }

        print(json.dumps({
            "valid": valid,
            "signature_count": 1,
            "signatures": [result],
            "message": (
                "The Chairperson's PDF signature passed verification."
                if valid
                else "The PDF signature failed one or more verification checks."
            ),
        }))

    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except Exception as exception:
        print(json.dumps({
            "valid": False,
            "message": str(exception),
            "signature_count": 0,
            "signatures": [],
        }))
        sys.exit(1)
