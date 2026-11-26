<?php

namespace App\Services\SigningCertificate;

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class PnpkiPdfVerificationService
{
    public function verify(
        string $pdfPath,
        string $expectedFingerprint,
        string $registeredCertificatePath,
    ): array {
        if (! config('pnpki.verification.enabled')) {
            throw ValidationException::withMessages([
                'signed_document' => 'PNPKI verification is currently disabled.',
            ]);
        }

        $python = config('pnpki.python_binary');
        $script = config('pnpki.script');
        $trustDirectory = config('pnpki.trust_directory');
        $certificatesDirectory = config('pnpki.certificates_directory');

        if (
            ! File::exists($pdfPath)
            || ! File::exists($registeredCertificatePath)
        ) {
            throw ValidationException::withMessages([
                'signed_document' => 'The PDF or registered signing certificate could not be found.',
            ]);
        }

        if (! File::exists($python) || ! File::exists($script)) {
            throw ValidationException::withMessages([
                'signed_document' => 'The PNPKI verification engine is not properly configured.',
            ]);
        }

        if (
            ! File::isDirectory($trustDirectory)
            || ! File::isDirectory($certificatesDirectory)
        ) {
            throw ValidationException::withMessages([
                'signed_document' => 'The PNPKI certificate directories are not properly configured.',
            ]);
        }

        $expectedFingerprint = strtoupper(
            str_replace([':', ' '], '', $expectedFingerprint)
        );

        if (! preg_match('/^[A-F0-9]{64}$/', $expectedFingerprint)) {
            throw ValidationException::withMessages([
                'signed_document' => 'The registered signing certificate fingerprint is invalid.',
            ]);
        }

        $environment = [];

        if (PHP_OS_FAMILY === 'Windows') {
            $systemRoot = getenv('SystemRoot') ?: getenv('SYSTEMROOT');
            $windowsDir = getenv('WINDIR') ?: $systemRoot;

            if ($systemRoot) {
                $environment['SystemRoot'] = $systemRoot;
            }

            if ($windowsDir) {
                $environment['WINDIR'] = $windowsDir;
            }
        }

        $process = new Process(
            [
                $python,
                $script,
                '--pdf',
                $pdfPath,
                '--expected-fingerprint',
                $expectedFingerprint,
                '--registered-cert',
                $registeredCertificatePath,
                '--trust-dir',
                $trustDirectory,
                '--cert-dir',
                $certificatesDirectory,
            ],
            base_path(),
            $environment
        );

        $process->setTimeout(
            (int) config('pnpki.verification.timeout', 60)
        );

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw ValidationException::withMessages([
                'signed_document' => 'PDF signature verification timed out.',
            ]);
        }

        $output = trim($process->getOutput());
        $result = json_decode($output, true);

        if (
            ! is_array($result)
            || ! array_key_exists('valid', $result)
        ) {
            throw ValidationException::withMessages([
                'signed_document' => 'The PNPKI verification engine returned an invalid response.',
            ]);
        }

        if (! $process->isSuccessful() || $result['valid'] !== true) {
            return [
                'valid' => false,
                'message' => $result['message']
                    ?? $result['error']
                    ?? 'The PDF signature failed verification.',
                'signature_count' => $result['signature_count'] ?? 0,
                'signatures' => $result['signatures'] ?? [],
            ];
        }

        return $result;
    }
}
