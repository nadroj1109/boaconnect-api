<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        {{ $request->documentType?->document_title ?? $request->documentType?->name ?? 'Barangay Document' }}
    </title>

    <style>

        @page {
            margin: 18mm 20mm 18mm 20mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .document-header {
            width: 100%;
            border-collapse: collapse;
        }

        .document-header td {
            vertical-align: middle;
        }

        .logo-cell {
            width: 20%;
            text-align: center;
        }

        .logo {
            width: 25mm;
            height: 25mm;
        }

        .header-content {
            width: 60%;
            text-align: center;
            line-height: 1.35;
        }

        .header-content .republic {
            font-weight: bold;
        }

        .header-content .barangay {
            font-weight: bold;
        }

        .header-content .office {
            margin-top: 5px;
            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | HEADER SEPARATOR
        |--------------------------------------------------------------------------
        */

        .header-line {
            border-top: 1px solid #000;
            margin-top: 6px;
            margin-bottom: 15px;
        }


        /*
        |--------------------------------------------------------------------------
        | DOCUMENT TITLE
        |--------------------------------------------------------------------------
        */

        .document-title {
            text-align: center;
            font-size: 17px;
            font-weight: bold;
            margin-bottom: 25px;
        }


        /*
        |--------------------------------------------------------------------------
        | MAIN CONTENT
        |--------------------------------------------------------------------------
        */

        .document-content {
            width: 100%;
            text-align: justify;
            line-height: 1.2;
        }

        .document-content p {
            margin-top: 0;
            margin-bottom: 0;
        }

        .document-content p:empty {
            min-height: 1.2em;
        }

        /*
        |--------------------------------------------------------------------------
        | BOTTOM DOCUMENT SECTION
        |--------------------------------------------------------------------------
        */

        .document-bottom {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
        }

        /*
        |--------------------------------------------------------------------------
        | SIGNATURE
        |--------------------------------------------------------------------------
        */

        .signature-section {
            width: 100%;
            margin: 0;
            page-break-inside: avoid;
        }

        .signature-table {
            width: 100%;
            border-collapse: collapse;
        }

        .signature-table td {
            vertical-align: bottom;
        }

        .signature-spacer {
            width: 55%;
        }

        .signature-block {
            width: 45%;
            text-align: center;
        }

        .signature-image {
            width: 50.8mm;
            height: auto;
            display: block;
            margin: 0 auto 2px;
        }

        .signature-placeholder {
            width: 50.8mm;
            height: 18mm;
            margin: 0 auto 2px;
            color: #777;
            font-style: italic;
            font-size: 10px;
            line-height: 18mm;
        }

        .signer-name {
            margin-top: 20px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .signer-position {
            margin-top: 0px;
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDITY
        |--------------------------------------------------------------------------
        */

        .validity-section {
            margin-top: 10px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            padding-bottom: 2mm;
        }


        /*
        |--------------------------------------------------------------------------
        | VERIFICATION QR
        |--------------------------------------------------------------------------
        */

        .verification-section {
            width: 100%;
            margin-top: 5px;
            text-align: right;
        }

        .verification-qr {
            width: 20mm;
            height: 20mm;
        }

        .verification-text {
            margin-top: 2px;
            font-size: 8px;
            color: #555;
        }

    </style>

</head>

<body>

    {{-- ============================================================
         HEADER
         ============================================================ --}}

    <table class="document-header">

        <tr>

            <td class="logo-cell">

                @if (!empty($logo_left))

                    <img
                        src="{{ $logo_left }}"
                        class="logo"
                    >

                @endif

            </td>


            <td class="header-content">

                <div class="republic">
                    REPUBLIC OF THE PHILIPPINES
                </div>

                <div>
                    PROVINCE OF
                    {{ strtoupper(
                        $request->barangay?->province?->province
                            ?? 'Marinduque'
                    ) }}
                </div>

                <div>
                    MUNICIPALITY/CITY OF
                    {{ strtoupper(
                        $request->barangay?->city?->city
                            ?? 'Boac'
                    ) }}
                </div>

                <div class="barangay">
                    BARANGAY
                    {{ strtoupper(
                        $request->barangay?->barangay
                            ?? '[BARANGAY NAME]'
                    ) }}
                </div>

                <div class="office">
                    OFFICE OF THE PUNONG BARANGAY
                </div>

            </td>


            <td class="logo-cell">

                @if (!empty($logo_right))

                    <img
                        src="{{ $logo_right }}"
                        class="logo"
                    >

                @endif

            </td>

        </tr>

    </table>


    {{-- ============================================================
         HEADER SEPARATOR
         ============================================================ --}}

    <div class="header-line"></div>


    {{-- ============================================================
         DOCUMENT TITLE
         ============================================================ --}}

    <div class="document-title">

        {{ strtoupper(
            $request->documentType?->document_title
                ?? $request->documentType?->name
                ?? 'BARANGAY DOCUMENT'
        ) }}

    </div>


    {{-- ============================================================
         DYNAMIC DOCUMENT CONTENT
         ============================================================ --}}

    <div class="document-content">

        {!! $content !!}

    </div>


    {{-- ============================================================
     BOTTOM SECTION
     ============================================================ --}}

    <div class="document-bottom">

        {{-- SIGNATURE --}}

        <div class="signature-section">

            <table class="signature-table">

                <tr>

                    <td class="signature-spacer"></td>

                    <td class="signature-block">

                        @if (!empty($signature_data))

                            <img
                                src="{{ $signature_data }}"
                                class="signature-image"
                            >

                        @elseif (!empty($preview))

                            <div class="signature-placeholder">
                                [Signature]
                            </div>

                        @endif

                        <div class="signer-name">
                            {{ $signer_name ?? '[Name of Punong Barangay]' }}
                        </div>

                        <div class="signer-position">
                            Punong Barangay
                        </div>

                    </td>

                </tr>

            </table>

        </div>


        {{-- SEAL NOTICE: PICKUP ONLY --}}
        @if ($request->delivery_method === \App\Enums\DocumentDeliveryMethod::PICKUP)

            <div class="validity-section">
                NOT VALID WITHOUT SEAL
            </div>

        @endif


        {{-- VERIFICATION QR --}}

        @if (!empty($verification_qr))

            <div class="verification-section">

                <img
                    src="{{ $verification_qr }}"
                    class="verification-qr"
                    alt="Document Verification QR Code"
                >

                <div class="verification-text">
                    Scan to verify this document
                </div>

            </div>

        @endif

    </div>

</body>

</html>