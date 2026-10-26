<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgramResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'owner_tutor_profile_id' => (int) $this->owner_tutor_profile_id,
            'customer_id' => (int) $this->customer_id,
            'source_listing_id' => $this->source_listing_id === null ? null : (int) $this->source_listing_id,
            'status' => $this->status,
            'version' => (int) $this->version,
            'title' => $this->title,
            'goal' => $this->goal,
            'level' => $this->level,
            'delivery_mode' => $this->delivery_mode,
            'agreed_fee_minor' => (int) $this->agreed_fee_minor,
            'currency' => $this->currency,
            'fee_unit' => $this->fee_unit,
            'location_text' => $this->location_text,
            'meeting_url' => $this->meeting_url,
            'schedule_note' => $this->schedule_note,
            'timezone' => $this->timezone,
            'planned_session_count' => $this->planned_session_count === null
                ? null
                : (int) $this->planned_session_count,
            'allowed_actions' => $this->allowedActions(),
        ];
    }

    /** @return list<string> */
    private function allowedActions(): array
    {
        if (
            ! $this->can_edit_draft
            || $this->status !== 'draft'
            || (bool) $this->has_unexpired_pending_invitation
        ) {
            return [];
        }

        return ['edit'];
    }
}
