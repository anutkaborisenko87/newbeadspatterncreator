<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\CommentReportResource;
use Filament\Resources\Pages\ListRecords;

class ListCommentReports extends ListRecords
{
    protected static string $resource = CommentReportResource::class;
}
