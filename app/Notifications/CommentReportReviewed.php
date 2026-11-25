<?php

namespace App\Notifications;

use App\Models\CommentReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CommentReportReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CommentReport $report)
    {
        $this->onQueue('report-mail');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('reports.reviewed'))
            ->line(__('reports.reviewed').' #'.$this->report->id.' · '.$this->report->reported_login)
            ->line(__('reports.statuses.'.$this->report->status))
            ->line($this->report->decision)
            ->action(__('reports.mine'), route('profile.index').'#my-reports');
    }
}
