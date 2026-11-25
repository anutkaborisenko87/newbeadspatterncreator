<?php

namespace App\Contracts\Services;

use App\Models\Pattern;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

interface PatternReportServiceInterface
{
    /**
     * Submit a report for a pattern.
     *
     * @param Pattern $pattern The pattern being reported.
     * @param User $reporter The user submitting the report.
     * @param array $input The input data for the report, including 'topic' and 'message'.
     *
     * @throws ValidationException If there is a validation error.
     * @throws ModelNotFoundException If the pattern is not found.
     * @throws HttpException If the reporter is the author of the pattern.
     */
    public function submit(Pattern $pattern, User $reporter, array $input):
    void;
}
