<?php

namespace Nawasara\Registry\Exceptions;

use RuntimeException;

/**
 * Anything that stops a SIMAS Hebat lookup from producing employment data.
 *
 * `blocked` is its own code because it needs a different message on screen:
 * the source firewall cuts the whole IP off after a few requests, and when it
 * does, even NIPs that worked a moment ago come back 403. Telling an admin
 * "pegawai tidak ditemukan" there would send them hunting for a data problem
 * that does not exist.
 */
class SimasHebatException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'simas_error',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public static function notConfigured(): static
    {
        return new static(
            'Kredensial SIMAS Hebat belum diatur di Vault (grup `simas-hebat`).',
            'simas_not_configured',
        );
    }

    public static function unreachable(?\Throwable $previous = null): static
    {
        return new static(
            'SIMAS Hebat tidak dapat dihubungi. Coba lagi beberapa saat.',
            'simas_unreachable',
            $previous,
        );
    }

    public static function blocked(): static
    {
        return new static(
            'Akses ke SIMAS Hebat sedang diblokir dari server ini. '
            .'Tunggu beberapa saat sebelum mencoba lagi.',
            'simas_blocked',
        );
    }

    public static function authFailed(): static
    {
        return new static(
            'Token SIMAS Hebat ditolak. Periksa kredensial di Vault.',
            'simas_auth_failed',
        );
    }

    public static function notFound(string $nip): static
    {
        return new static(
            "NIP {$nip} tidak ditemukan di SIMAS Hebat.",
            'simas_not_found',
        );
    }

    public static function unexpectedShape(): static
    {
        return new static(
            'Jawaban SIMAS Hebat tidak berbentuk yang dikenali.',
            'simas_unexpected_shape',
        );
    }
}
