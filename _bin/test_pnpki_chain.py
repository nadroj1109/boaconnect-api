import asyncio
import json
from pathlib import Path

from asn1crypto import pem, x509
from pyhanko_certvalidator import CertificateValidator, ValidationContext


BASE = Path("storage/app/pnpki")

REGISTERED_CERT = Path(
    "storage/app/private/signing-certificates/6/"
    "12664fd7-6306-4b13-9ee3-8429f802f258.cer"
)

TRUST_DIR = BASE / "trust"
CERT_DIR = BASE / "certificates"


def load_certificate(path):
    data = path.read_bytes()

    if pem.detect(data):
        _, _, data = pem.unarmor(data)

    return x509.Certificate.load(data)


async def main():
    certificate = load_certificate(REGISTERED_CERT)

    trust_roots = [
        load_certificate(path)
        for path in TRUST_DIR.glob("*")
        if path.suffix.lower() in (".cer", ".crt", ".pem")
    ]

    intermediates = [
        load_certificate(path)
        for path in CERT_DIR.glob("*")
        if path.suffix.lower() in (".cer", ".crt", ".pem")
    ]

    print("Registered certificate:", certificate.subject.human_friendly)
    print("Trust roots:", len(trust_roots))
    print("Intermediate certificates:", len(intermediates))

    if not trust_roots:
        raise RuntimeError("No trust root certificates found.")

    context = ValidationContext(
        trust_roots=trust_roots,
        other_certs=intermediates,
        allow_fetching=True,
        revocation_mode="require",
        retroactive_revinfo=False,
    )

    try:
        validator = CertificateValidator(
            end_entity_cert=certificate,
            intermediate_certs=intermediates,
            validation_context=context,
        )

        validated_path = await validator.async_validate_usage(
            key_usage={"digital_signature", "non_repudiation"},
            extended_optional=True,
        )

        print("\nVALIDATION PASSED")
        print("Trust chain and required revocation checks passed.")
        print("Validated path:", validated_path)

    finally:
        await context.aclose()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except Exception as exception:
        print("\nVALIDATION FAILED")
        print("Error type:", type(exception).__name__)
        print("Error:", str(exception))
