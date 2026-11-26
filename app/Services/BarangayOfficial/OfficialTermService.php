<?php

namespace App\Services\BarangayOfficial;

use App\Models\BarangayOfficial;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

class OfficialTermService
{
    public function terminateExpiredTerms(): int
    {
        $today = now()->toDateString();

        $expiredOfficialIds = BarangayOfficial::query()
            ->where('is_active', true)
            ->whereNotNull('term_end')
            ->whereDate('term_end', '<', $today)
            ->pluck('id');

        $terminated = 0;

        foreach ($expiredOfficialIds as $officialId) {
            $auditData = DB::transaction(function () use (
                $officialId,
                $today
            ) {
                $official = BarangayOfficial::query()
                    ->whereKey($officialId)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$official ||
                    !$official->is_active ||
                    !$official->term_end ||
                    $official->term_end->toDateString() >= $today
                ) {
                    return null;
                }

                $official->update([
                    'is_active' => false,
                ]);

                $resident = $official->resident;

                $userDeactivated = false;
                $tokensRevoked = 0;
                $userId = null;

                if ($resident) {
                    $hasAnotherActiveTerm = BarangayOfficial::query()
                        ->where('resident_id', $resident->id)
                        ->where('is_active', true)
                        ->whereDate('term_start', '<=', $today)
                        ->whereDate('term_end', '>=', $today)
                        ->exists();

                    if (!$hasAnotherActiveTerm) {
                        $user = User::query()
                            ->where('resident_id', $resident->id)
                            ->lockForUpdate()
                            ->first();

                        if ($user) {
                            $userId = $user->id;

                            if ($user->is_active) {
                                $user->update([
                                    'is_active' => false,
                                ]);

                                $userDeactivated = true;
                            }

                            // Revoke any remaining sessions, even if
                            // the account was already inactive.
                            $tokensRevoked = $user->tokens()->count();

                            $user->revokeAllTokens();
                        }
                    }
                }

                return [
                    'official' => $official,
                    'old_values' => [
                        'is_active' => true,
                    ],
                    'new_values' => [
                        'is_active' => false,
                        'official_id' => $official->id,
                        'resident_id' => $official->resident_id,
                        'barangay_id' => $official->barangay_id,
                        'official_position_id' =>
                            $official->official_position_id,
                        'term_end' => $official->term_end
                            ->toDateString(),
                        'linked_user_id' => $userId,
                        'linked_user_deactivated' => $userDeactivated,
                        'tokens_revoked' => $tokensRevoked,
                        'trigger' => 'scheduled_termination',
                    ],
                ];
            });

            // The transaction has committed before the custom event
            // is dispatched. No event is recorded for a skipped row.
            if ($auditData === null) {
                continue;
            }

            /** @var BarangayOfficial $official */
            $official = $auditData['official'];

            $official->auditEvent = 'term_ended';
            $official->isCustomEvent = true;
            $official->auditCustomOld = $auditData['old_values'];
            $official->auditCustomNew = $auditData['new_values'];

            Event::dispatch(new AuditCustom($official));

            $terminated++;
        }

        return $terminated;
    }
}
