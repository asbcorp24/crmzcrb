<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Workshop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('admin.dashboard', [
            'workshops'=>Workshop::withCount(['users'])->orderBy('name')->get(),
            'usersCount'=>User::where('role','employee')->count(),
            'testsCount'=>DB::table('tests')->count(),
            'attemptsCount'=>DB::table('attempts')->where('status','submitted')->count(),
        ]);
    }

    public function workshops()
    {
        return view('admin.workshops', ['workshops'=>Workshop::withCount('users')->orderBy('name')->get()]);
    }

    public function storeWorkshop(Request $request)
    {
        $data=$request->validate([
            'name'=>'required|string|max:255|unique:workshops,name',
            'code'=>'nullable|string|max:50|unique:workshops,code',
        ]);
        Workshop::create($data+['is_active'=>true]);
        return back()->with('success','Цех создан.');
    }

    public function updateWorkshop(Request $request, Workshop $workshop)
    {
        $data=$request->validate([
            'name'=>['required','string','max:255',Rule::unique('workshops','name')->ignore($workshop->id)],
            'code'=>['nullable','string','max:50',Rule::unique('workshops','code')->ignore($workshop->id)],
            'is_active'=>'nullable|boolean',
        ]);
        $data['is_active']=$request->boolean('is_active');
        $workshop->update($data);
        return back()->with('success','Цех обновлён.');
    }

    public function users(Request $request)
    {
        $q=User::with('workshop')->where('role','<>','superadmin');
        if($request->filled('workshop_id')) $q->where('workshop_id',$request->integer('workshop_id'));
        if($request->filled('q')) {
            $term=trim((string)$request->q);
            $q->where(fn($w)=>$w->where('full_name','like','%'.$term.'%')->orWhere('login','like','%'.$term.'%'));
        }
        $users=$q->orderBy('full_name')->paginate(50)->withQueryString();
        $passwords=[];
        foreach($users as $user) $passwords[$user->id]=$user->visiblePassword();
        return view('admin.users',[
            'users'=>$users,
            'passwords'=>$passwords,
            'workshops'=>Workshop::where('is_active',true)->orderBy('name')->get(),
        ]);
    }

    public function storeUser(Request $request)
    {
        $data=$request->validate([
            'workshop_id'=>'required|exists:workshops,id',
            'full_name'=>'required|string|max:255',
            'login'=>'required|string|max:100|unique:users,login',
            'position'=>'nullable|string|max:255',
            'password'=>'nullable|string|min:4|max:100',
        ]);
        $password=trim((string)($data['password']??'')) ?: Str::upper(Str::random(4)).random_int(1000,9999);
        $user=new User([
            'workshop_id'=>$data['workshop_id'],
            'full_name'=>$data['full_name'],
            'login'=>$data['login'],
            'position'=>$data['position']??null,
            'role'=>'employee',
            'is_active'=>true,
        ]);
        $user->setVisiblePassword($password);
        $user->save();
        return back()->with('success','Сотрудник создан. Пароль: '.$password);
    }

    public function bulkUsers(Request $request)
    {
        $data=$request->validate([
            'workshop_id'=>'required|exists:workshops,id',
            'people'=>'required|string',
        ]);

        $created=0;
        $skipped=[];
        foreach(preg_split('/\\r?\\n/',trim($data['people'])) as $line){
            $line=trim($line); if($line==='') continue;
            $parts=array_map('trim',explode('|',$line));
            $fio=$parts[0]??'';
            if($fio==='') continue;

            $base=Str::slug($parts[1]??$fio,'');
            if($base==='') $base='user';
            $login=$parts[1]??$base;
            $login=mb_strtolower(trim($login));
            $candidate=$login; $n=1;
            while(User::where('login',$candidate)->exists()) $candidate=$login.(++$n);
            $login=$candidate;

            $password=trim((string)($parts[2]??'')) ?: Str::upper(Str::random(4)).random_int(1000,9999);

            $user=new User([
                'workshop_id'=>$data['workshop_id'],
                'full_name'=>$fio,
                'login'=>$login,
                'role'=>'employee',
                'is_active'=>true,
            ]);
            $user->setVisiblePassword($password);
            $user->save();
            $created++;
        }

        return back()->with('success','Создано сотрудников: '.$created.'.');
    }

    public function credentialsCsv()
    {
        $users=User::with('workshop')->where('role','employee')->orderBy('workshop_id')->orderBy('full_name')->get();
        $callback=function() use($users){
            $out=fopen('php://output','w');
            fwrite($out,"\\xEF\\xBB\\xBF");
            fputcsv($out,['Цех','ФИО','Должность','Логин','Пароль'],';');
            foreach($users as $user){
                fputcsv($out,[
                    $user->workshop?->name,
                    $user->full_name,
                    $user->position,
                    $user->login,
                    $user->visiblePassword(),
                ],';');
            }
            fclose($out);
        };

        return response()->streamDownload($callback,'credentials-'.now()->format('Y-m-d-His').'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8',
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(),422);
        $data=$request->validate([
            'workshop_id'=>'required|exists:workshops,id',
            'full_name'=>'required|string|max:255',
            'login'=>['required','string','max:100',Rule::unique('users','login')->ignore($user->id)],
            'position'=>'nullable|string|max:255',
            'is_active'=>'nullable|boolean',
        ]);
        $data['is_active']=$request->boolean('is_active');
        $user->update($data);
        return back()->with('success','Сотрудник обновлён.');
    }

    public function resetPassword(Request $request, User $user)
    {
        abort_if($user->isSuperAdmin(),422);
        $data=$request->validate(['password'=>'nullable|string|min:4|max:100']);
        $password=trim((string)($data['password']??'')) ?: Str::upper(Str::random(4)).random_int(1000,9999);
        $user->setVisiblePassword($password);
        $user->save();
        return back()->with('success','Новый пароль для '.$user->full_name.': '.$password);
    }
}
