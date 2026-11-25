<?php

namespace App\Contracts\Services;

use App\Models\Pattern;
use App\Models\User;

interface CommentReportServiceInterface
{
    /**
     * Creates a report for a comment associated with the given pattern.
     *
     * @param User $reporter The user submitting the report.
     * @param Pattern $pattern The pattern that contains the reported comment.
     * @param int $commentId The identifier of the comment being reported.
     * @param array $input The report data used to create the report.
     *
     * @return void
     */
    public function create(User $reporter, Pattern $pattern, int $commentId,
                           array $input): void;
}
