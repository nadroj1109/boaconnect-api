import argparse
import asyncio
import json
import sys
from pathlib import Path

from asn1crypto import pem, x509
from pyhanko_certvalidator import (
    CertificateValidator,
    ValidationContext,
)


def load_certificate(file_path):
    data = Path(file_path).read_bytes()

    if pem.detect(data):
        _, _, data = pem.unarmor(data)

    return x509.Certificate.load(data)


def load_certificates(directory):
    path = Path(directory)

    if not path.is_dir():
        return []

    return [
        load_certificate(cert_file)
        for cert_file in path.iterdir()
        if cert_file.suffix.lower() in (".cer", ".crt", ".pem")
    ]


async def validate(args):
    context = None

    try:
        certificate = load_certificate(args.registered_cert)

        trust_roots = load_certificates(args.trust_dir)
        intermediates = load_certificates(args.cert_dir)

        if not trust_roots:
            raise RuntimeError(
                "No PNPKI trust root certificates configured."
            )

        context = ValidationContext(
            trust_roots=trust_roots,
            other_certs=intermediates,
            allow_fetching=True,
            revocation_mode="require",
            retroactive_revinfo=False,
        )

        validator = CertificateValidator(
            end_entity_cert=certificate,
            intermediate_certs=intermediates,
            validation_context=context,
        )

        validated_path = await validator.async_validate_usage(
            key_usage={
                "digital_signature",
                "non_repudiation",
            },
            extended_optional=True,
        )

        return {
            "valid": True,
            "trust_chain": {
                "passed": True,
                "message": "PNPKI certificate trust chain validated successfully.",
            },
            "revocation": {
                "passed": True,
                "message": "Required certificate revocation checks passed.",
            },
            "message": "PNPKI certificate validation passed.",
        }

    except Exception as exception:
        message = str(exception)

        return {
            "valid": False,
            "trust_chain": {
                "passed": False,
                "message": message,
            },
            "revocation": {
                "passed": False,
                "message": message,
            },
            "message": "PNPKI trust-chain and revocation validation failed.",
        }

    finally:
        if context is not None:
            await context.aclose()


def main():
    parser = argparse.ArgumentParser()

    parser.add_argument("--registered-cert", required=True)
    parser.add_argument("--trust-dir", required=True)
    parser.add_argument("--cert-dir", required=True)

    args = parser.parse_args()

    result = asyncio.run(validate(args))

    print(json.dumps(result))

    return 0 if result["valid"] else 1


if __name__ == "__main__":
    try:
        sys.exit(main())

    except Exception as exception:
        print(json.dumps({
            "valid": False,
            "message": str(exception),
            "trust_chain": {
                "passed": False,
                "message": "Certificate validation could not be completed.",
            },
            "revocation": {
                "passed": False,
                "message": "Certificate validation could not be completed.",
            },
        }))

        sys.exit(1)
