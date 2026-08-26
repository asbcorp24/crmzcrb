<?php

namespace App\Console\Commands;

use App\Http\Controllers\TaskTemplateController;
use App\Models\TaskTemplate;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'crm:recurring-tasks';
    protected $description = 'Создаёт задачи по активным повторяющимся шаблонам';

    public function handle(TaskTemplateController $controller): int
    {
        $templates = TaskTemplate::with('checklistItems')
            ->where('is_active', true)
            ->where('recurrence', '!=', 'none')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($templates as $template) {
            $controller->makeTask($template, $template->created_by);
            $next = $template->next_run_at->copy();
            $interval = max(1, (int)$template->recurrence_interval);

            if ($template->recurrence === 'daily') {
                $next->addDays($interval);
            } elseif ($template->recurrence === 'weekly') {
                $next->addWeeks($interval);
                if ($template->weekday) $next->nextOrSame($template->weekday);
            } elseif ($template->recurrence === 'monthly') {
                $next->addMonthsNoOverflow($interval);
                if ($template->day_of_month) $next->day(min($template->day_of_month, $next->daysInMonth));
            }

            while ($next->lte(now())) {
                if ($template->recurrence === 'daily') $next->addDays($interval);
                elseif ($template->recurrence === 'weekly') $next->addWeeks($interval);
                else $next->addMonthsNoOverflow($interval);
            }

            $template->update(['next_run_at' => $next]);
            $this->line('Создана задача по шаблону #'.$template->id.'; следующий запуск '.$next->format('d.m.Y H:i'));
        }

        $this->info('Обработано шаблонов: '.$templates->count());
        return self::SUCCESS;
    }
}
