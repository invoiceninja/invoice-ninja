<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Task;

use App\Models\Task;
use App\Libraries\MultiDB;
use App\Models\CompanyUser;
use App\Services\Email\Email;
use Illuminate\Bus\Queueable;
use App\Services\Email\EmailObject;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Utils\Traits\Notifications\UserNotifies;

class TaskAssigned implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UserNotifies;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(private Task $task, private string $db)
    {
    }

    public function handle(): void
    {

        MultiDB::setDb($this->db);

        $company_user = $this->task->assignedCompanyUser();

        if (($company_user instanceof CompanyUser) && $this->findEntityAssignedNotification($company_user, 'task')) {
            $mo = new EmailObject();
            $mo->subject = ctrans('texts.task_assigned_subject', ['task' => $this->task->number, 'date' => now()->setTimeZone($this->task->company->timezone()->name)->format($this->task->company->date_format()) ]);
            $mo->body = ctrans('texts.task_assigned_body', ['task' => $this->task->number, 'description' => $this->task->description ?? '', 'client' => $this->task->client ? $this->task->client->present()->name() : ' ']);
            $mo->text_body = ctrans('texts.task_assigned_body', ['task' => $this->task->number, 'description' => $this->task->description ?? '', 'client' => $this->task->client ? $this->task->client->present()->name() : ' ']);
            $mo->company_key = $this->task->company->company_key;
            $mo->html_template = 'email.template.generic';
            $mo->to = [new Address($this->task->assigned_user->email, $this->task->assigned_user->present()->name())];
            $mo->email_template_body = 'task_assigned_body';
            $mo->email_template_subject = 'task_assigned_subject';

            (new Email($mo, $this->task->company))->handle();

        }

    }

    public function failed($exception = null)
    {
    }
}
