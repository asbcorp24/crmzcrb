<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\EmployeeAbsence;
use App\Models\EmployeeAssignment;
use App\Models\EmployeeSubstitution;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Plan;
use App\Models\Position;
use App\Models\StaffingPosition;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskEvent;
use App\Models\TaskOverdueReason;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateChecklistItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $password = Hash::make('Demo12345!');

            // Организационная структура
            $administration = Department::updateOrCreate(
                ['name' => 'Администрация'],
                ['short_name'=>'Администрация','type'=>'administration','is_active'=>true,'sort_order'=>10]
            );
            $medical = Department::updateOrCreate(
                ['name' => '[ДЕМО] Медицинская служба'],
                ['parent_id'=>$administration->id,'short_name'=>'Медслужба','type'=>'service','is_active'=>true,'sort_order'=>20]
            );
            $reception = Department::updateOrCreate(
                ['name' => '[ДЕМО] Приёмное отделение'],
                ['parent_id'=>$medical->id,'short_name'=>'Приёмное','type'=>'department','is_active'=>true,'sort_order'=>30]
            );
            $statistics = Department::updateOrCreate(
                ['name' => '[ДЕМО] Отдел медицинской статистики'],
                ['parent_id'=>$administration->id,'short_name'=>'Статистика','type'=>'department','is_active'=>true,'sort_order'=>40]
            );
            $it = Department::updateOrCreate(
                ['name' => '[ДЕМО] ИТ-служба'],
                ['parent_id'=>$administration->id,'short_name'=>'ИТ','type'=>'service','is_active'=>true,'sort_order'=>50]
            );

            // Пользователи. У всех демо-пользователей один пароль: Demo12345!
            $admin = User::updateOrCreate(
                ['email'=>'admin@zcrb.local'],
                ['department_id'=>$administration->id,'manager_id'=>null,'last_name'=>'Администратор','first_name'=>'Системы','middle_name'=>null,'position'=>'Администратор CRM','phone'=>'','role'=>'admin','is_active'=>true,'employment_date'=>now()->subYears(5)->toDateString(),'password'=>$password]
            );
            $chief = User::updateOrCreate(
                ['email'=>'chief.demo@zcrb.local'],
                ['department_id'=>$administration->id,'manager_id'=>$admin->id,'last_name'=>'Смирнов','first_name'=>'Алексей','middle_name'=>'Викторович','position'=>'Заместитель главного врача','phone'=>'+7 900 100-00-01','role'=>'manager','is_active'=>true,'employment_date'=>now()->subYears(8)->toDateString(),'password'=>$password]
            );
            $headReception = User::updateOrCreate(
                ['email'=>'head.reception.demo@zcrb.local'],
                ['department_id'=>$reception->id,'manager_id'=>$chief->id,'last_name'=>'Иванова','first_name'=>'Марина','middle_name'=>'Сергеевна','position'=>'Заведующая приёмным отделением','phone'=>'+7 900 100-00-02','role'=>'manager','is_active'=>true,'employment_date'=>now()->subYears(6)->toDateString(),'password'=>$password]
            );
            $surgeon = User::updateOrCreate(
                ['email'=>'surgeon.demo@zcrb.local'],
                ['department_id'=>$reception->id,'manager_id'=>$headReception->id,'last_name'=>'Петров','first_name'=>'Илья','middle_name'=>'Андреевич','position'=>'Врач-хирург','phone'=>'+7 900 100-00-03','role'=>'employee','is_active'=>true,'employment_date'=>now()->subYears(3)->toDateString(),'password'=>$password]
            );
            $nurse = User::updateOrCreate(
                ['email'=>'nurse.demo@zcrb.local'],
                ['department_id'=>$reception->id,'manager_id'=>$headReception->id,'last_name'=>'Соколова','first_name'=>'Анна','middle_name'=>'Олеговна','position'=>'Старшая медицинская сестра','phone'=>'+7 900 100-00-04','role'=>'employee','is_active'=>true,'employment_date'=>now()->subYears(4)->toDateString(),'password'=>$password]
            );
            $stat = User::updateOrCreate(
                ['email'=>'stat.demo@zcrb.local'],
                ['department_id'=>$statistics->id,'manager_id'=>$chief->id,'last_name'=>'Кузнецова','first_name'=>'Елена','middle_name'=>'Павловна','position'=>'Медицинский статистик','phone'=>'+7 900 100-00-05','role'=>'employee','is_active'=>true,'employment_date'=>now()->subYears(2)->toDateString(),'password'=>$password]
            );
            $itUser = User::updateOrCreate(
                ['email'=>'it.demo@zcrb.local'],
                ['department_id'=>$it->id,'manager_id'=>$chief->id,'last_name'=>'Орлов','first_name'=>'Дмитрий','middle_name'=>'Игоревич','position'=>'Инженер-программист','phone'=>'+7 900 100-00-06','role'=>'employee','is_active'=>true,'employment_date'=>now()->subYear()->toDateString(),'password'=>$password]
            );

            // Справочник должностей и штатное расписание
            $positions = [
                'head' => Position::updateOrCreate(['code'=>'DEMO-HEAD'], ['name'=>'Заведующий отделением','category'=>'Руководители','is_active'=>true]),
                'doctor' => Position::updateOrCreate(['code'=>'DEMO-DOCTOR'], ['name'=>'Врач-хирург','category'=>'Врачи','is_active'=>true]),
                'nurse' => Position::updateOrCreate(['code'=>'DEMO-NURSE'], ['name'=>'Старшая медицинская сестра','category'=>'Средний медперсонал','is_active'=>true]),
                'stat' => Position::updateOrCreate(['code'=>'DEMO-STAT'], ['name'=>'Медицинский статистик','category'=>'Специалисты','is_active'=>true]),
                'it' => Position::updateOrCreate(['code'=>'DEMO-IT'], ['name'=>'Инженер-программист','category'=>'Специалисты','is_active'=>true]),
            ];

            $staffRows = [
                [$reception, $positions['head'], 1.00, $headReception],
                [$reception, $positions['doctor'], 2.00, $surgeon], // одна ставка останется вакантной
                [$reception, $positions['nurse'], 1.00, $nurse],
                [$statistics, $positions['stat'], 1.50, $stat], // 0.5 ставки вакантно
                [$it, $positions['it'], 2.00, $itUser], // одна ставка вакантна
            ];
            foreach ($staffRows as [$department, $position, $rate, $employee]) {
                $row = StaffingPosition::updateOrCreate(
                    ['department_id'=>$department->id,'position_id'=>$position->id],
                    ['planned_rate'=>$rate,'note'=>'[ДЕМО] Штатная позиция','is_active'=>true]
                );
                EmployeeAssignment::updateOrCreate(
                    ['user_id'=>$employee->id,'staffing_position_id'=>$row->id,'started_at'=>now()->subYear()->toDateString()],
                    ['rate'=>1.00,'is_primary'=>true,'ended_at'=>null,'order_number'=>'ДЕМО-001','note'=>'[ДЕМО] Основное место работы']
                );
            }

            // Планы
            $receptionPlan = Plan::updateOrCreate(
                ['user_id'=>$headReception->id,'title'=>'[ДЕМО] План работы приёмного отделения'],
                ['created_by'=>$chief->id,'description'=>'Месячный план организационных задач приёмного отделения.','period_start'=>now()->startOfMonth()->toDateString(),'period_end'=>now()->endOfMonth()->toDateString(),'period_type'=>'month','status'=>'active','progress'=>0]
            );
            $statPlan = Plan::updateOrCreate(
                ['user_id'=>$stat->id,'title'=>'[ДЕМО] Подготовка месячной отчётности'],
                ['created_by'=>$chief->id,'description'=>'Сверка и подготовка управленческой отчётности за месяц.','period_start'=>now()->startOfMonth()->toDateString(),'period_end'=>now()->endOfMonth()->toDateString(),'period_type'=>'month','status'=>'active','progress'=>0]
            );

            // Задачи с разными состояниями
            $completed = $this->task($receptionPlan, $chief, $headReception, '[ДЕМО] Обновить график дежурств', 'completed', 100, now()->subDays(4), 'normal', now()->subDays(3));
            $progress = $this->task($receptionPlan, $headReception, $surgeon, '[ДЕМО] Проверить маршрутизацию пациентов', 'in_progress', 60, now()->addDays(3), 'high');
            $review = $this->task($receptionPlan, $headReception, $nurse, '[ДЕМО] Актуализировать перечень оснащения', 'review', 100, now()->addDay(), 'normal');
            $overdue = $this->task($statPlan, $chief, $stat, '[ДЕМО] Сверить реестры за отчётный период', 'in_progress', 40, now()->subDays(3), 'critical');
            $itTask = $this->task(null, $chief, $itUser, '[ДЕМО] Проверить резервное копирование CRM', 'new', 0, now()->addDays(2), 'critical');
            $personal = $this->task(null, $surgeon, $surgeon, '[ДЕМО] Подготовить предложения по улучшению маршрутизации', 'new', 0, now()->addDays(5), 'normal');

            // Чек-лист и комментарии
            $this->checklist($progress, [
                ['Проверить текущую схему маршрутизации', true, $surgeon],
                ['Собрать замечания сотрудников', true, $surgeon],
                ['Подготовить итоговые предложения', false, null],
            ]);
            $this->checklist($review, [
                ['Сверить перечень оборудования', true, $nurse],
                ['Указать отсутствующие позиции', true, $nurse],
                ['Передать результат руководителю', true, $nurse],
            ]);

            TaskComment::firstOrCreate(['task_id'=>$progress->id,'user_id'=>$headReception->id,'body'=>'[ДЕМО] Учтите замечания по вечерней смене.']);
            TaskComment::firstOrCreate(['task_id'=>$progress->id,'user_id'=>$surgeon->id,'body'=>'[ДЕМО] Основная схема проверена, готовлю предложения.']);
            TaskComment::firstOrCreate(['task_id'=>$review->id,'user_id'=>$nurse->id,'body'=>'[ДЕМО] Перечень актуализирован и готов к проверке.']);
            TaskOverdueReason::updateOrCreate(
                ['task_id'=>$overdue->id,'user_id'=>$stat->id],
                ['reason_code'=>'waiting_data','comment'=>'[ДЕМО] Ожидаются уточнённые данные от подразделения.']
            );

            // Повторяющийся шаблон
            $template = TaskTemplate::updateOrCreate(
                ['created_by'=>$headReception->id,'title'=>'[ДЕМО] Еженедельная проверка журнала'],
                ['assigned_to'=>$nurse->id,'description'=>'Проверить заполнение журнала и сообщить о замечаниях.','priority'=>'normal','due_after_days'=>1,'recurrence'=>'weekly','recurrence_interval'=>1,'weekday'=>1,'day_of_month'=>null,'next_run_at'=>now()->next('Monday')->setTime(8,0),'is_active'=>true]
            );
            foreach (['Проверить полноту записей','Проверить подписи ответственных','Сообщить о выявленных замечаниях'] as $i => $title) {
                TaskTemplateChecklistItem::updateOrCreate(['task_template_id'=>$template->id,'title'=>$title], ['sort_order'=>$i]);
            }

            // Отпуск и замещение в ближайшем будущем
            $absence = EmployeeAbsence::updateOrCreate(
                ['user_id'=>$surgeon->id,'type'=>'vacation','date_from'=>now()->addDays(7)->toDateString(),'date_to'=>now()->addDays(14)->toDateString()],
                ['document_number'=>'ДЕМО-ОТП-01','comment'=>'[ДЕМО] Плановый отпуск','created_by'=>$headReception->id]
            );
            EmployeeSubstitution::updateOrCreate(
                ['absent_user_id'=>$surgeon->id,'substitute_user_id'=>$headReception->id,'date_from'=>$absence->date_from->toDateString(),'date_to'=>$absence->date_to->toDateString()],
                ['comment'=>'[ДЕМО] Замещение на период отпуска','created_by'=>$chief->id]
            );

            // Совещание и связанное поручение
            $meeting = Meeting::updateOrCreate(
                ['title'=>'[ДЕМО] Еженедельное оперативное совещание','held_at'=>now()->startOfWeek()->addDays(1)->setTime(9,0)],
                ['location'=>'Кабинет заместителя главного врача','chairman_id'=>$chief->id,'secretary_id'=>$stat->id,'created_by'=>$chief->id,'notes'=>'[ДЕМО] Сроки, просрочки, кадровая доступность и текущие поручения.','status'=>'active']
            );
            $meeting->participants()->syncWithoutDetaching([$headReception->id,$surgeon->id,$nurse->id,$stat->id,$itUser->id]);
            MeetingItem::updateOrCreate(
                ['meeting_id'=>$meeting->id,'number'=>1],
                ['instruction'=>'[ДЕМО] Проверить резервное копирование CRM и представить результат.','assigned_to'=>$itUser->id,'due_at'=>$itTask->due_at,'priority'=>'critical','task_id'=>$itTask->id,'created_by'=>$chief->id]
            );

            // Несколько событий для истории задач
            foreach ([$completed,$progress,$review,$overdue,$itTask,$personal] as $task) {
                TaskEvent::firstOrCreate(
                    ['task_id'=>$task->id,'user_id'=>$task->created_by,'type'=>'created','message'=>'[ДЕМО] Задача создана демонстрационным сидером'],
                    ['from_status'=>null,'to_status'=>'new']
                );
            }

            // Финальный пересчёт гарантирует корректные проценты планов даже после повторного запуска сидера.
            $receptionPlan->recalculateProgress();
            $statPlan->recalculateProgress();
        });

        $this->command?->info('Демо-данные CRM созданы. Пароль демо-пользователей: Demo12345!');
    }

    private function task(?Plan $plan, User $creator, User $assignee, string $title, string $status, int $progress, $dueAt, string $priority, $completedAt = null): Task
    {
        return Task::updateOrCreate(
            ['title'=>$title,'assigned_to'=>$assignee->id],
            [
                'plan_id'=>$plan?->id,
                'created_by'=>$creator->id,
                'description'=>'[ДЕМО] Демонстрационная задача для проверки рабочего процесса CRM.',
                'priority'=>$priority,
                'status'=>$status,
                'progress'=>$progress,
                'started_at'=>$progress > 0 ? now()->subDays(2) : null,
                'due_at'=>$dueAt,
                'completed_at'=>$completedAt,
                'result'=>$status === 'completed' ? '[ДЕМО] Работа выполнена.' : ($status === 'review' ? '[ДЕМО] Результат передан руководителю на проверку.' : null),
            ]
        );
    }

    private function checklist(Task $task, array $items): void
    {
        foreach ($items as $i => [$title, $done, $user]) {
            TaskChecklistItem::updateOrCreate(
                ['task_id'=>$task->id,'title'=>$title],
                ['sort_order'=>$i,'is_done'=>$done,'completed_by'=>$done ? $user?->id : null,'completed_at'=>$done ? now()->subDay() : null]
            );
        }
    }
}
