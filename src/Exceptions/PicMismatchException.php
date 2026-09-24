<?php

namespace Nawasara\Registry\Exceptions;

use Nawasara\Registry\Models\Opd;
use RuntimeException;

/**
 * SIMAS Hebat places this person in a different OPD than the one recorded.
 *
 * Thrown rather than returned on purpose. A return value can be ignored
 * without consequence, and what hangs on this one is who may read an OPD's
 * data: ScopedToOpd filters rows by opd_id, so silently accepting either
 * answer hands somebody the wrong data. An exception forces the caller to
 * decide, and the caller is a screen with an admin in front of it.
 *
 * It is NOT resolved automatically. Overwriting opd_id would revoke someone's
 * access with nobody told, and if the prefix matcher is the thing that slipped,
 * the access revoked is the correct one.
 */
class PicMismatchException extends RuntimeException
{
    public function __construct(
        public readonly Opd $recorded,
        public readonly ?Opd $fromSimas,
        public readonly string $simasUnitKerja,
    ) {
        parent::__construct(sprintf(
            'OPD berbeda: tercatat "%s", menurut SIMAS Hebat "%s".',
            $recorded->name,
            $fromSimas?->name ?? $simasUnitKerja,
        ));
    }
}
