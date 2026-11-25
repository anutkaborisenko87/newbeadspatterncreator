<?php

namespace App\Notifications;

use App\Models\PatternReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatternReportSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public PatternReport $report)
    {
        $this->onQueue('report-mail');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $notifiable->id == $this->report->reporter_id ? 'submitted' : 'received';
        return (new MailMessage)->subject(__('pattern_reports.'.$label))
            ->line(__('pattern_reports.'.$label).' · '.$this->report->title.' (#'.$this->report->id.')')
            ->action(__('pattern_reports.heading'), route('profile.index').'#pattern-reports');
    }
}
