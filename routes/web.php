<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-python-asyncio', function () {
    $systemRoot = getenv('SYSTEMROOT')
        ?: getenv('SystemRoot')
        ?: 'C:\\Windows';

    $process = new \Symfony\Component\Process\Process(
        [
            config('pnpki.python_binary'),
            '-c',
            'import os; print("SYSTEMROOT:", repr(os.environ.get("SYSTEMROOT"))); import asyncio; print("ASYNCIO_OK")',
        ],
        null,
        [
            'SystemRoot' => $systemRoot,
            'WINDIR' => $systemRoot,
        ]
    );

    $process->setTimeout(15);
    $process->run();

    return response()->json([
        'successful' => $process->isSuccessful(),
        'exit_code' => $process->getExitCode(),
        'output' => $process->getOutput(),
        'error' => $process->getErrorOutput(),
    ]);
});
