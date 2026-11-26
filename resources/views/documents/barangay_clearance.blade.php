<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <title>Barangay Clearance</title>

    <style>
        @page {
            margin: 25mm 20mm 20mm 20mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.6;
            color: #000;
        }

        .header {
            text-align: center;
            line-height: 1.4;
        }

        .header .republic {
            font-weight: bold;
        }

        .header .barangay {
            font-size: 15px;
            font-weight: bold;
        }

        .header .office {
            font-weight: bold;
        }

        .title {
            margin-top: 30px;
            margin-bottom: 25px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
        }

        .to-whom {
            font-weight: bold;
            margin-bottom: 20px;
        }

        .content {
            text-align: justify;
        }

        .content p {
            margin-bottom: 18px;
        }

        .signature {
            margin-top: 45px;
            text-align: center;
        }

        .signature .name {
            font-weight: bold;
            text-transform: uppercase;
        }

        .signature-image {
            width: 50.8mm;
            height: auto;
            display: block;
            margin: 0 auto 5px;
        }

        .signature-placeholder {
            width: 50.8mm;
            height: 20mm;
            margin: 0 auto 5px;
            color: #777;
            font-style: italic;
            font-size: 10px;
            line-height: 20mm;
        }

        .details {
            margin-top: 40px;
        }

        .details table {
            width: 100%;
            border-collapse: collapse;
        }

        .details td {
            padding: 3px 0;
            vertical-align: top;
        }

        .details .label {
            width: 130px;
            font-weight: bold;
        }

        .placeholder {
            font-style: italic;
        }
    </style>
</head>

<body>

    {{-- HEADER --}}

    <div class="header">

        <div class="republic">
            Republic of the Philippines
        </div>

        <div>
            Province of
            {{ $request->barangay?->province?->name ?? '[Province Name]' }}
        </div>

        <div>
            Municipality/City of
            {{ $request->barangay?->city?->name ?? '[City/Municipality Name]' }}
        </div>

        <div class="barangay">
            BARANGAY
            {{ $request->barangay?->name ?? '[Barangay Name]' }}
        </div>

        <div class="office">
            OFFICE OF THE PUNONG BARANGAY
        </div>

    </div>


    {{-- TITLE --}}

    <div class="title">
        BARANGAY CLEARANCE
    </div>


    {{-- BODY --}}

    <div class="to-whom">
        TO WHOM IT MAY CONCERN:
    </div>

    <div class="content">

        @php
            $applicant = $request->requestedFor
                ?? $request->resident;

            $fullName = $applicant?->full_name
                ?? '[Full Name of Applicant]';

            $age = $applicant?->age
                ?? '[Age]';

            $civilStatus = $applicant?->civil_status?->value
                ?? '[Civil Status]';

            $barangayName =
                $request->barangay?->name
                ?? '[Barangay Name]';

            $cityName =
                $request->barangay?->city?->name
                ?? '[City/Municipality]';

            $provinceName =
                $request->barangay?->province?->name
                ?? '[Province]';

            $purpose =
                $request->purpose
                ?? '[State Purpose]';
        @endphp

        <p>
            THIS IS TO CERTIFY that
            <strong>{{ $fullName }}</strong>,
            <strong>{{ $age }}</strong> years old,
            <strong>{{ $civilStatus }}</strong>,
            a Filipino citizen, is a bonafide resident of
            Barangay {{ $barangayName }},
            {{ $cityName }},
            {{ $provinceName }}.
        </p>

        <p>
            Further certified that he/she has a good moral character
            and is a law-abiding citizen in the community. Based on
            the records kept in this office, he/she has
            <strong>no derogatory record</strong> or pending
            criminal/civil complaint filed against him/her as of
            this date.
        </p>

        <p>
            This clearance is issued upon the request of the
            above-named person for
            <strong>{{ $purpose }}</strong>.
        </p>

        <p>
            ISSUED this
            <strong>{{ now()->format('jS') }}</strong>
            day of
            <strong>{{ now()->format('F') }}</strong>,
            <strong>{{ now()->format('Y') }}</strong>
            at Barangay {{ $barangayName }},
            {{ $cityName }},
            {{ $provinceName }},
            Philippines.
        </p>

    </div>


    {{-- SIGNATURE --}}

    <div class="signature">

        @if (!empty($signature_data))
            <img
                src="{{ $signature_data }}"
                class="signature-image"
            >
        @elseif (!empty($preview))
            <div class="signature-placeholder">
                [Chairperson Signature]
            </div>
        @endif

        <div class="name">
            {{ $signer_name ?? '[Name of Punong Barangay]' }}
        </div>

        <div>
            Punong Barangay
        </div>

    </div>


    {{-- CTC / RECEIPT DETAILS --}}

    <div class="details">

        <table>
            <tr>
                <td class="label">
                    CTC No.:
                </td>
                <td>
                    <span class="placeholder">
                        [Community Tax Certificate Number]
                    </span>
                </td>
            </tr>

            <tr>
                <td class="label">
                    Issued On:
                </td>
                <td>
                    <span class="placeholder">
                        [Date Issued]
                    </span>
                </td>
            </tr>

            <tr>
                <td class="label">
                    Issued At:
                </td>
                <td>
                    <span class="placeholder">
                        [Place Issued]
                    </span>
                </td>
            </tr>

            <tr>
                <td class="label">
                    O.R. No.:
                </td>
                <td>
                    <span class="placeholder">
                        [Official Receipt Number]
                    </span>
                </td>
            </tr>
        </table>

    </div>

    @if (!empty($verification_qr))
        <div style="
            margin-top: 20px;
            text-align: right;
        ">
            <img
                src="{{ $verification_qr }}"
                alt="Document Verification QR Code"
                style="
                    width: 90px;
                    height: 90px;
                "
            >

            <div style="
                margin-top: 4px;
                font-size: 8px;
                color: #555;
            ">
                Scan to verify this document
            </div>
        </div>
    @endif

</body>
</html>