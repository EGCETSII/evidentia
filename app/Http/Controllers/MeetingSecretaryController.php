<?php

namespace App\Http\Controllers;

use App\Http\Services\UserService;
use App\Models\Agreement;
use App\Models\DefaultList;
use App\Models\Diary;
use App\Models\DiaryPoints;
use App\Models\Meeting;
use App\Models\MeetingMinutes;
use App\Models\MeetingRequest;
use App\Models\Point;
use App\Models\SignatureSheet;
use App\Rules\CheckHoursAndMinutes;
use App\Models\User;
use Barryvdh\DomPDF\Facade as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MeetingSecretaryController extends Controller
{

    private $user_service;

    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('checkroles:SECRETARY');
        $this->user_service = new UserService();
    }

    public function manage()
    {
        $instance = \Instantiation::instance();

        return view('meeting.manage',['instance' => $instance]);
    }

    /*
     *  Requests
     */
    public function request_list()
    {

        $instance = \Instantiation::instance();

        $meeting_requests = Auth::user()->secretary->meeting_requests;

        return view('meeting.request_list',["meeting_requests" => $meeting_requests, "instance" => $instance]);
    }

    public function request_create()
    {
        $instance = \Instantiation::instance();

        return view('meeting.request',['instance' => $instance]);
    }

    public function request_new(Request $request_http)
    {
        $instance = \Instantiation::instance();

        $request_http->validate([
            'title' => 'required|min:5|max:255',
            'place' => 'required|min:5|max:255',
            'date' => 'required|date_format:Y-m-d|after:yesterday',
            'time' => 'required',
            'type' => 'required|numeric|min:1|max:2',
            'modality' => 'required|numeric|min:1|max:3',
            'points_list' => 'required'
        ]);

        $meeting_request = MeetingRequest::create([
            'title' => $request_http->input('title'),
            'place' => $request_http->input('place'),
            'datetime' => $request_http->input('date')." ".$request_http->input('time'),
            'type' => $request_http->input('type'),
            'modality' => $request_http->input('modality'),
            'comittee_id' => Auth::user()->secretary->comittee->id,
            'secretary_id' => Auth::user()->secretary->id
        ]);

        $diary = Diary::create([
            "meeting_request_id" => $meeting_request->id
        ]);

        foreach (json_decode($request_http->input('points_list'),1) as $key => $value) {

            DiaryPoints::create([
                "diary_id" => $diary->id,
                "point" => $value
            ]);
        }

        // Genera PDF de la convocatoria
        $pdf = PDF::loadView('meeting.request_template', ['meeting_request' => $meeting_request]);
        $content = $pdf->download()->getOriginalContent();
        Storage::put(\Instantiation::instance() .'/meeting_requests/meeting_request_' .$meeting_request->id . '.pdf',$content) ;

        return redirect()->route('secretary.meeting.manage.request.list',$instance)->with('success', 'Convocatoria de reunión creada con éxito.');
    }

    public function request_download($instance, $id)
    {
        $meeting_request = MeetingRequest::findOrFail($id);

        $response = Storage::download(\Instantiation::instance() .'/meeting_requests/meeting_request_' .$meeting_request->id . '.pdf');

        // limpiar búfer de salida
        ob_end_clean();

        return $response;
    }

    public function request_remove(Request $request)
    {
        $meeting_request = MeetingRequest::where('id',$request->input('meeting_request_id'))->first();

        $instance = \Instantiation::instance();

        // borramos el pdf del acta antigua
        Storage::delete(\Instantiation::instance() .'/meeting_requests/meeting_request_' .$meeting_request->id . '.pdf');

        // desemparejamos signature sheet
        $signature_sheet = $meeting_request->signature_sheet;
        $signature_sheet->meeting_request_id = null;
        $signature_sheet->save();

        // eliminamos la entidad en sí
        $meeting_request->delete();

        return redirect()->route('secretary.meeting.manage.request.list',$instance)->with('success', 'Convocatoria eliminada con éxito.');
    }

    /*
     *  Signature sheets
     */
    public function signaturesheet_list()
    {
        $instance = \Instantiation::instance();

        $signature_sheets = Auth::user()->secretary->signature_sheets;

        return view('meeting.signaturesheet_list',["instance" => $instance, 'signature_sheets' => $signature_sheets]);
    }

    public function signaturesheet_create()
    {
        $instance = \Instantiation::instance();

        $available_meeting_requests = Auth::user()->secretary->meeting_requests;

        $available_meeting_requests = $available_meeting_requests->filter(function($value,$key){
            return $value->signature_sheet == null;
        });

        return view('meeting.signaturesheet_create',['instance' => $instance, 'available_meeting_requests' => $available_meeting_requests]);
    }

    public function signaturesheet_new(Request $request)
    {
        $instance = \Instantiation::instance();

        $request->validate([
            'title' => 'required|min:5|max:255'
        ]);

        // generamos identificador aleatorio y comprobamos si ya está ocupado
        $random_identifier = \Random::getRandomIdentifier('4');
        $signature_sheet_with_random_identifier = SignatureSheet::where('random_identifier', $random_identifier)->first();
        if($signature_sheet_with_random_identifier != null){

        }

        $signature_sheet = SignatureSheet::create([
            'title' => $request->input('title'),
            'random_identifier' => $this->generate_random_identifier_for_signature(4),
            'meeting_request_id' => $request->input('meeting_request'),
            'secretary_id' => Auth::user()->secretary->id
        ]);

        return redirect()->route('secretary.meeting.manage.signaturesheet.list',$instance)->with('success', 'Reunión creada con éxito.');

    }

    private function generate_random_identifier_for_signature($number)
    {
        $random_identifier = \Random::getRandomIdentifier('4');
        $signature_sheet_with_random_identifier = SignatureSheet::where('random_identifier', $random_identifier)->first();

        if($signature_sheet_with_random_identifier != null){
            return $this->generate_random_identifier_for_signature($number);
        }

        return $random_identifier;
    }

    public function signaturesheet_view($instance,$signature_sheet)
    {
        $signature_sheet = SignatureSheet::findOrFail($signature_sheet);

        return view('meeting.signaturesheet_view',["instance" => $instance, 'signature_sheet' => $signature_sheet]);
    }

    /*
     *  Minutes
     */
    public function minutes_list()
    {
        $instance = \Instantiation::instance();

        $meeting_minutes = Auth::user()->secretary->meeting_minutes;

        return view('meeting.minutes_list',["instance" => $instance, 'meeting_minutes' => $meeting_minutes]);
    }
    public function minutes_create()
    {
        $instance = \Instantiation::instance();

        return redirect()->route('secretary.meeting.manage.minutes.create.step1',['instance' => $instance]);
    }

    public function minutes_create_step1()
    {
        $instance = \Instantiation::instance();

        $meeting_requests = Auth::user()->secretary->meeting_requests;

        return view('meeting.minutes_create_step1',[
            'instance' => $instance,
            'meeting_requests' => $meeting_requests
        ]);
    }

    public function minutes_create_step1_p(Request $request)
    {
        $instance = \Instantiation::instance();

        $meeting_request = MeetingRequest::find($request->input('meeting_request'));

        return redirect()->route('secretary.meeting.manage.minutes.create.step2',[
            'instance' => $instance,
            'meeting_request' => $meeting_request
        ]);
    }

    public function minutes_create_step2(Request $request)
    {
        $instance = \Instantiation::instance();

        $meeting_request = MeetingRequest::find($request->input('meeting_request'));

        $signature_sheets = Auth::user()->secretary->signature_sheets;

        return view('meeting.minutes_create_step2',[
            'instance' => $instance,
            'meeting_request' => $meeting_request,
            'signature_sheets' => $signature_sheets
        ]);
    }

    public function minutes_create_step2_p(Request $request)
    {
        $instance = \Instantiation::instance();

        $meeting_request_input = $request->input('meeting_request');
        $signature_sheet_input = $request->input('signature_sheet');

        $meeting_request = MeetingRequest::find($meeting_request_input);
        $signature_sheet = SignatureSheet::find($signature_sheet_input);

        if($signature_sheet != null){

            // si la hoja de firmas tiene una convocatoria asociada, se descarta cualquier otra elegida
            // por el secretario
            if($signature_sheet->meeting_request != null){
                $meeting_request = $signature_sheet->meeting_request;
            }

        }

        return redirect()->route('secretary.meeting.manage.minutes.create.step3',[
            'instance' => $instance,
            'meeting_request' => $meeting_request,
            'signature_sheet' => $signature_sheet
        ]);
    }

    public function minutes_create_step3(Request $request)
    {
        $instance = \Instantiation::instance();

        $meeting_request = MeetingRequest::find($request->input('meeting_request'));
        $signature_sheet = SignatureSheet::find($request->input('signature_sheet'));

        $users = $this->user_service->all_except_logged();
        $defaultlists = Auth::user()->secretary->default_lists;

        return view('meeting.minutes_create_step3',[
            'instance' => $instance,
            'meeting_request' => $meeting_request,
            'signature_sheet' => $signature_sheet,
            'users' => $users,
            'defaultlists' => $defaultlists
        ]);
    }

    public function minutes_create_step3_p(Request $request)
    {

        $instance = \Instantiation::instance();
        $minutes = $request->input('minutes');

        $validator = Validator::make($request->all(), [
            'title' => 'required|min:5|max:255',
            'type' => 'required|numeric|min:1|max:2',
            'hours' => ['required_without:minutes','nullable','numeric','sometimes','max:99',new CheckHoursAndMinutes($request->input('minutes'))],
            'minutes' => ['required_without:hours','nullable','numeric','sometimes','max:60',new CheckHoursAndMinutes($request->input('hours'))],
            'place' => 'required|min:5|max:255',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required',
            'users' => 'required|array|min:1'
        ]);

        if ($validator->fails()) {
            $points = json_decode($request->input('points_json'),true);
            return back()->withErrors($validator)->withInput()->with([
                'error' => 'Hay errores en el formulario.',
                'points' => collect($points)
            ]);
        }

        $meeting = Meeting::create([
            'title' => $request->input('title'),
            'hours' => $request->input('hours') + floor(($minutes*100)/60)/100,
            'type' => $request->input('type'),
            'modality' => $request->input('modality'),
            'place' => $request->input('place'),
            'datetime' => $request->input('date')." ".$request->input('time')
        ]);

        $meeting->comittee()->associate(Auth::user()->secretary->comittee);

        $meeting->save();

        // Asociamos los usuarios a la reunión
        $users_ids = $request->input('users',[]);
        foreach($users_ids as $user_id)
        {

            $user = User::find($user_id);
            $meeting->users()->attach($user);

        }

        // Añadimos el secretario a la reunión
        $meeting->users()->attach(Auth::user()->secretary->user);

        // Guardamos los puntos y los acuerdos tomados
        $meeting_minutes = MeetingMinutes::create([
            'meeting_id' => $meeting->id,
            'secretary_id' => Auth::user()->secretary->id
        ]);

        $points = json_decode($request->input('points_json'),true);
        $points = collect($points);

        foreach($points as $point){
            $new_point = Point::create([
                'meeting_minutes_id' => $meeting_minutes->id,
                'title' => $point['title'],
                'duration' => $point['duration'] == '' ? 0 : $point['duration'],
                'description' => $point['description']
            ]);

            foreach($point['agreements'] as $agreement){

                $new_agreement = Agreement::create([
                    'point_id' => $new_point->id,
                    'description' => $agreement['description']
                ]);

                // generamos el identificador único para este acuerdo
                $identificator = "ISD";
                $identificator .= '-';
                $identificator .= Carbon::now()->format('Y-m-d');
                $identificator .= '-';
                $identificator .= Auth::user()->secretary->comittee->id;
                $identificator .= '-';
                $identificator .= $meeting->id;
                $identificator .= '-';
                $identificator .= $new_point->id;
                $identificator .= '-';
                $identificator .= $new_agreement->id;

                $new_agreement->identificator = $identificator;
                $new_agreement->save();
            }
        }

        // Genera PDF del acta
        $pdf = PDF::loadView('meeting.minutes_template', ['meeting_minutes' => $meeting_minutes]);
        $content = $pdf->download()->getOriginalContent();
        Storage::put(\Instantiation::instance() .'/meeting_minutes/meeting_minutes_' .$meeting_minutes->id . '.pdf',$content) ;

        return redirect()->route('secretary.meeting.manage.minutes.list',$instance)->with('success', 'Acta de reunión creada con éxito.');

    }

    public function minutes_edit($instance,$id)
    {
        $instance = \Instantiation::instance();

        $meeting_minutes = MeetingMinutes::findOrFail($id);

        $points_array = array();

        foreach ($meeting_minutes->points as $point) {

            $agreements_array = array();

            foreach($point->agreements as $agreement){
                $agreement_array = array(
                    'description' => $agreement->description
                );
                array_push($agreements_array,$agreement_array);
            }

            $point_array = array(
                'id' => $point->id,
                'title' => $point->title,
                'description' => $point->description,
                'duration' => $point->duration,
                'agreements' => $agreements_array
            );

            array_push($points_array,$point_array);
        }

        $points = collect($points_array);

        $users = $this->user_service->all_except_logged();
        $defaultlists = Auth::user()->secretary->default_lists;

        return view('meeting.minutes_edit', [
            'instance' => $instance,
            'meeting_minutes' => $meeting_minutes,
            'points' => $points,
            'users' => $users,
            'defaultlists' => $defaultlists
        ]);

    }

    public function minutes_save(Request $request)
    {

        $instance = \Instantiation::instance();
        $minutes = $request->input('minutes');

        $validator = Validator::make($request->all(), [
            'title' => 'required|min:5|max:255',
            'type' => 'required|numeric|min:1|max:2',
            'hours' => ['required_without:minutes','nullable','numeric','sometimes','max:99',new CheckHoursAndMinutes($request->input('minutes'))],
            'minutes' => ['required_without:hours','nullable','numeric','sometimes','max:60',new CheckHoursAndMinutes($request->input('hours'))],
            'place' => 'required|min:5|max:255',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required',
            'users' => 'required|array|min:1'
        ]);

        if ($validator->fails()) {
            $points = json_decode($request->input('points_json'),true);
            return back()->withErrors($validator)->withInput()->with([
                'error' => 'Hay errores en el formulario.',
                'points' => collect($points)
            ]);
        }

        // modificamos la info básica de la reunión
        $meeting = Meeting::where('id',$request->input('meeting_id'))->first();
        $meeting->title = $request->input('title');
        $meeting->hours = $request->input('hours') + floor(($minutes*100)/60)/100;
        $meeting->type = $request->input('type');
        $meeting->modality = $request->input('modality');
        $meeting->place = $request->input('place');
        $meeting->datetime = $request->input('date')." ".$request->input('time');
        $meeting->save();

        // Asociamos los usuarios a la reunión
        $users_ids = $request->input('users',[]);

        // eliminamos usuarios antiguos de la reunión
        foreach($meeting->users as $user)
        {
            $meeting->users()->detach($user);
        }

        // agregamos los usuarios nuevos de la reunión
        foreach($users_ids as $user_id)
        {
            $user = User::find($user_id);
            $meeting->users()->attach($user);
        }

        // borramos los puntos y acuerdos previos
        if($meeting->meeting_minutes->points){
            foreach($meeting->meeting_minutes->points as $point){
                foreach($point->agreements as $agreement){
                    $agreement->delete();
                }
                $point->delete();
            }
        }

        // borramos el pdf del acta antigua
        Storage::delete(\Instantiation::instance() .'/meeting_minutes/meeting_minutes_' .$meeting->meeting_minutes->id . '.pdf');

        // borramos el acta antigua
        $meeting->meeting_minutes->delete();

        // Añadimos el secretario a la reunión
        $meeting->users()->attach(Auth::user()->secretary->user);

        // Guardamos los puntos y los acuerdos tomados
        $meeting_minutes = MeetingMinutes::create([
            'meeting_id' => $meeting->id,
            'secretary_id' => Auth::user()->secretary->id
        ]);

        $points = json_decode($request->input('points_json'),true);
        $points = collect($points);

        foreach($points as $point){
            $new_point = Point::create([
                'meeting_minutes_id' => $meeting_minutes->id,
                'title' => $point['title'],
                'duration' => $point['duration'] == '' ? 0 : $point['duration'],
                'description' => $point['description']
            ]);

            foreach($point['agreements'] as $agreement){

                $new_agreement = Agreement::create([
                    'point_id' => $new_point->id,
                    'description' => $agreement['description']
                ]);

                // generamos el identificador único para este acuerdo
                $identificator = "ISD";
                $identificator .= '-';
                $identificator .= Carbon::now()->format('Y-m-d');
                $identificator .= '-';
                $identificator .= Auth::user()->secretary->comittee->id;
                $identificator .= '-';
                $identificator .= $meeting->id;
                $identificator .= '-';
                $identificator .= $new_point->id;
                $identificator .= '-';
                $identificator .= $new_agreement->id;

                $new_agreement->identificator = $identificator;
                $new_agreement->save();
            }
        }

        // Generamos de nuevo el PDF
        $pdf = PDF::loadView('meeting.minutes_template', ['meeting_minutes' => $meeting_minutes]);
        $content = $pdf->download()->getOriginalContent();
        Storage::put(\Instantiation::instance() .'/meeting_minutes/meeting_minutes_' .$meeting_minutes->id . '.pdf',$content);

        return redirect()->route('secretary.meeting.manage.minutes.list',$instance)->with('success', 'Acta de reunión editada con éxito.');
    }

    public function minutes_remove(Request $request)
    {
        $meeting_minutes = MeetingMinutes::where('id',$request->input('meeting_minutes_id'))->first();

        $instance = \Instantiation::instance();

        // borramos el pdf del acta antigua
        Storage::delete(\Instantiation::instance() .'/meeting_minutes/meeting_minutes_' .$meeting_minutes->id . '.pdf');

        $meeting_minutes->meeting->delete();
        $meeting_minutes->delete();

        return redirect()->route('secretary.meeting.manage.minutes.list',$instance)->with('success', 'Acta de reunión eliminada con éxito.');

    }

    public function minutes_download($instance, $id)
    {
        $meeting_minutes = MeetingMinutes::findOrFail($id);

        $response = Storage::download(\Instantiation::instance() .'/meeting_minutes/meeting_minutes_' .$meeting_minutes->id . '.pdf');

        // limpiar búfer de salida
        ob_end_clean();

        return $response;
    }

    /*
    public function list()
    {
        $instance = \Instantiation::instance();

        $meetings = Auth::user()->secretary->comittee->meetings()->get();

        return view('meeting.list',
            ['instance' => $instance, 'meetings' => $meetings]);
    }
    */

    /*
    public function create()
    {
        $instance = \Instantiation::instance();

        $users = User::orderBy('surname')->get();
        $defaultlists = Auth::user()->secretary->default_lists;

        return view('meeting.createandedit',
            ['instance' => $instance, 'users' => $users, 'defaultlists' => $defaultlists, 'route' => route('secretary.meeting.new',$instance)]);
    }
    */

    /*
    public function new(Request $request)
    {

        $instance = \Instantiation::instance();
        $minutes = $request->input('minutes');

        $validatedData = $request->validate([
            'title' => 'required|min:5|max:255',
            'type' => 'required|numeric|min:1|max:2',
            'hours' => ['required_without:minutes','nullable','numeric','sometimes','max:99',new CheckHoursAndMinutes($request->input('minutes'))],
            'minutes' => ['required_without:hours','nullable','numeric','sometimes','max:60',new CheckHoursAndMinutes($request->input('hours'))],
            'place' => 'required|min:5|max:255',
            'date' => 'required|date_format:Y-m-d|before:tomorrow',
            'time' => 'required',
            'users' => 'required|array|min:1'
        ]);

        $meeting = Meeting::create([
            'title' => $request->input('title'),
            'hours' => $request->input('hours') + floor(($minutes*100)/60)/100,
            'type' => $request->input('type'),
            'place' => $request->input('place'),
            'datetime' => $request->input('date')." ".$request->input('time')
        ]);

        $meeting->comittee()->associate(Auth::user()->secretary->comittee);

        $meeting->save();

        // Asociamos los usuarios a la reunión
        $users_ids = $request->input('users',[]);

        foreach($users_ids as $user_id)
        {

            $user = User::find($user_id);
            $meeting->users()->attach($user);

        }

        return redirect()->route('secretary.meeting.list',$instance)->with('success', 'Reunión creada con éxito.');

    }
    */

    /*
    public function edit($instance,$id)
    {
        $meeting = Meeting::find($id);
        $users = User::orderBy('surname')->get();
        $defaultlists = Auth::user()->secretary->default_lists;

        return view('meeting.createandedit',
            ['instance' => $instance, 'meeting' => $meeting, 'edit' => true, 'users' => $users, 'defaultlists' => $defaultlists, 'route' => route('secretary.meeting.save',$instance)]);
    }
    */

    public function defaultlist($instance,$id)
    {
        return DefaultList::find($id)->users;
    }

    /*
    public function save(Request $request)
    {

        $instance = \Instantiation::instance();
        $minutes = $request->input('minutes');

        $validatedData = $request->validate([
            'title' => 'required|min:5|max:255',
            'type' => 'required|numeric|min:1|max:2',
            'hours' => ['required_without:minutes','nullable','numeric','sometimes','max:99',new CheckHoursAndMinutes($request->input('minutes'))],
            'minutes' => ['required_without:hours','nullable','numeric','sometimes','max:60',new CheckHoursAndMinutes($request->input('hours'))],
            'place' => 'required|min:5|max:255',
            'date' => 'required|date_format:Y-m-d|before:tomorrow',
            'time' => 'required',
            'users' => 'required|array|min:1'
        ]);

        $meeting = Meeting::find($request->_id);
        $meeting->title = $request->input('title');
        $meeting->hours = $request->input('hours') + floor(($minutes*100)/60)/100;
        $meeting->type = $request->input('type');
        $meeting->place = $request->input('place');
        $meeting->datetime = $request->input('date')." ".$request->input('time');

        $meeting->save();

        // Asociamos los usuarios a la reunión
        $users_ids = $request->input('users',[]);

        // eliminamos usuarios antiguos de la reunión
        foreach($meeting->users as $user)
        {
            $meeting->users()->detach($user);
        }

        // agregamos los usuarios nuevos de la reunión
        foreach($users_ids as $user_id)
        {
            $user = User::find($user_id);
            $meeting->users()->attach($user);
        }

        return redirect()->route('secretary.meeting.list',$instance)->with('success', 'Reunión editada con éxito.');

    }
    */

    /*
    public function remove(Request $request)
    {
        $meeting = Meeting::find($request->_id);
        $instance = \Instantiation::instance();

        $meeting->delete();

        return redirect()->route('secretary.meeting.list',$instance)->with('success', 'Reunión eliminada con éxito.');
    }
    */
}
