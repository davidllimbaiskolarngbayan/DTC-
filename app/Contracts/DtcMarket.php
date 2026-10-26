<?php

namespace App\Contracts;

interface DtcMarket
{
    /** Validation rules for a public registration submitted from WordPress. */
    public function rules(): array;

    /** Validation rules for an admin edit of record $id. */
    public function updateRules(int $id, ?string $currentCardNo = null): array;

    /** Map submitted form fields to dtc_users columns. */
    public function setUser(array $request): void;

    public function getUser(): array;

    /** Subject and sender details for the thank-you email. */
    public function emailDetails(): array;

    /** dtc_users columns an admin may change. */
    public function editableFields(): array;
}
