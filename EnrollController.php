<?php

namespace App\Http\Controllers\employer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Booth;
use App\BoothAssistantZoom;
use App\BoothReserve;
use App\BoothTicketType;
use App\Category;
use App\EnrollReservation;
use App\EnrollPhoto;
use App\Imagetool;
use App\EnrollVideo;
use App\Employees;
use App\EnrollBanner;
use App\EnrollEmployerStream;
use App\EnrollGroupVideoChannel;
use App\EnrollInvoice;
use Pusher\Pusher;
use Symfony\Component\HttpFoundation\Response;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\library\EnrollSettings;
use Mail;
use App\library\Settings;

class EnrollController extends Controller
{



    public function addnew(Request $request)
    {

        $categories = Category::get();
        $booths = Booth::get();
        $datas['placeholder'] = Imagetool::mycrop('no-image.png', 60,60);


        if($request->isMethod('post')){

            $request->validate([
                'fair_detail' => 'required|mimes:pdf,doc,docx',
                // 'banner_image' => 'required',
                'exhibition_category' => 'required',
                'company_name' => 'required',
                'intro_video' => 'required',
                'description' => 'required',
                'booth_name' => 'required',
                'booth_type' => 'required',
                'item_price' => 'required',
                // 'zoom' => 'required',
                'company_site' => 'required',
                'photo' => 'required',
                'banner' => 'required',
                'seo_url' => 'required',
                'exhibition_date' => 'required',
                'to_date' => 'required',


            ]);

            $datas = $request->all();

            $reservation = new EnrollReservation();
            $reservation->category_id = $datas['exhibition_category'];
            $reservation->employer_id = auth()->guard('employer')->user()->employers_id;

            $reservation->company_name = $datas['company_name'];
            $reservation->seo_url = $datas['seo_url'];
            $reservation->company_website = $datas['company_site'];
            $intro_id = $this->YoutubeID($datas['intro_video']);
            $reservation->intro_video = $intro_id ;
            $reservation->start_date = $datas['exhibition_date'];
            $reservation->end_date = $datas['to_date'];
            // $reservation->banner_file = $datas['banner_image'];
            $reservation->description = $datas['description'];

            if($request['chat_facility'] != null)
            {
                $chat_f = 1;
            }else{
                $chat_f = 0;
            }
            $reservation->chat_facility = $chat_f;
            if($request['video_call'] != null)
            {
                $video_f = 1;
            }else{
                $video_f = 0;
            }
            $reservation->video_facility = $video_f;
            if($request['livestream'] != null)
            {
                $livestream_f = 1;
            }else{
                $livestream_f = 0;
            }
            $reservation->livestream_facility = $livestream_f;

            $reservation->payment_status = 0;

              //Fair detail
            if($request->hasFile('fair_detail'))
              {
                $file_temp = $request->file('fair_detail');
                if($file_temp->isValid()){
                    $filenameWithExtension = $file_temp->getClientOriginalName();
                    $extension = $file_temp->getClientOriginalExtension();
                    $filenameWithoutExtension = pathinfo($filenameWithExtension, PATHINFO_FILENAME);
                    $filenameToStore = $filenameWithoutExtension.'_'.time().'.'.$extension;
                    $path = $file_temp->move(DIR_IMAGE.'companies/fairDetails', $filenameToStore);
                    $reservation->fair_detail = $path;

                }

            }
            $reservation->save();
            $reservation_id = $reservation->id;

            //Video
            if(isset($request->video)){
                foreach($request->video as $key => $video){
                    if(trim($video['vlink']) != ''){
                        $vid = $this->YoutubeID($video['vlink']);
                        $data = [
                            'reservation_id' => $reservation_id,
                            'title' => $video['vtitle'],
                            'link' => $vid,
                        ];
                        EnrollVideo::create($data);
                    }
                }
            }
            //Photo
            if (isset($request->photo)) {
                foreach($request->photo as $key => $photo) {
                    if (trim($photo['title']) != '') {
                    $data = [
                      'reservation_id' => $reservation_id,
                      'title' => $photo['title'],
                      'image' => $photo['image'],
                      'description' => $photo['description']
                    ];
                    EnrollPhoto::create($data);
                  }
                }
            }
            //Banner
            if (isset($request->banner)) {
                foreach($request->banner as $key => $banner) {
                    if (trim($banner['title']) != '') {
                    $data = [
                      'reservation_id' => $reservation_id,
                      'title' => $banner['title'],
                      'image' => $banner['image'],
                    ];
                    \App\EnrollBanner::create($data);
                  }
                }
            }

            // Booth Reserve
            foreach ($datas['booth_name'] as $key=>$val)
            {

                $booth = new BoothReserve();
                $booth->reservation_id = $reservation_id;
                $booth->employer_id = auth()->guard('employer')->user()->employers_id;
                $temp_name = Booth::select('booth_name')->where('id', $val)->first();
                $booth->booth_name = $temp_name['booth_name'];
                $type_id = $datas['booth_type'][$key];
                $temp = BoothTicketType::select('ticket_name', 'price')->where('id', $type_id)->first();
                $booth->booth_type = $temp['ticket_name'];
                $booth->price = $temp['price'];
                $booth->save();
            }

            // zoom detail for Company Speaker in Booth
            // if (isset($request->zoom)) {
            //     foreach($request->zoom as $key => $zoom) {
            //         if (trim($zoom['zlink']) != '') {
            //         $data = [
            //           'reservation_id' => $reservation_id,
            //           'url' => $zoom['zlink'],
            //           'meeting_id' => $zoom['zid'],
            //           'password' => bcrypt($zoom['password'])
            //         ];
            //         BoothAssistantZoom::create($data);
            //       }
            //     }
            // }

            $total_price = BoothReserve::where('reservation_id', $reservation_id)->sum('price');
                EnrollReservation::where('id', $reservation_id)->update([
                    'total_price' => $total_price
            ]);
            $employer = \App\Employers::where('id',auth()->guard('employer')->user()->employers_id)->first();

            $mydata = array(
                'to_name' => $employer->name,
                'to_email' => $employer->email,
                'subject' => 'Booking Conformation for the Virtual Exhibition',
                'text' => 'This email is to notify that your booth(s) for the upcoming virtual exhibition has been successfully booked. We will reach to you soon for more details.',
                // 'invoice_detail' => EnrollInvoice::where('id',$invoice->id)->first(),
                'from_name' => Settings::getSettings()->name,
                'logo' => Settings::getImages()->logo,
                'from_email' => Settings::getSettings()->email,
                'store_address' => Settings::getSettings()->address,
                'store_phone' => Settings::getSettings()->telephone,
            );

            Mail::send('employer.enroll.mail.booking_confirmation', ['data' => $mydata], function($mail) use ($mydata){
                $mail->to($mydata['to_email'],$mydata['to_name'])->from($mydata['from_email'],$mydata['from_name'])->subject($mydata['subject']);
            });


            return redirect('employer/enroll/all-detail')->with('message', "Successufully reserved");

        }
        return view('employer.enroll.new_enroll', compact('categories', 'booths', 'datas'));
    }

    public function YoutubeID($url)
    {
        if(strlen($url) > 11)
        {
            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $url, $match))
            {
                return $match[1];
            }
            else
                return false;
        }

        return $url;
    }

    public function enrollDetail()
    {
        $reservations = EnrollReservation::where('employer_id', auth()->guard('employer')->user()->employers_id)
        ->with('boothreserves')
        ->orderBy('created_at', 'desc')
        ->get();
        return view('employer.enroll.enroll_detail', compact('reservations'));
    }

    public function editEnroll($id=null)
    {
        $datas['placeholder'] = Imagetool::mycrop('no-image.png', 60,60);

        $reservations = EnrollReservation::where('id', $id)
        ->with('photos')
        ->with('videos')
        ->with('banners')
        ->first();
        // dd($reservations);
        $categories = Category::get();
        return view('employer.enroll.editenroll', compact('reservations','categories', 'datas'));
    }

    public function updateEnroll(Request $request, $id=null)
    {
        $data = $request->all();
        if ($request->hasFile('fair_detail')) {
            $file_temp = $request->file('fair_detail');
            if ($file_temp->isValid()) {
                $filenameWithExtension = $file_temp->getClientOriginalName();
                $extension = $file_temp->getClientOriginalExtension();
                $filenameWithoutExtension = pathinfo($filenameWithExtension, PATHINFO_FILENAME);
                $filenameToStore = $filenameWithoutExtension.'_'.time().'.'.$extension;
                $path = $file_temp->move(DIR_IMAGE.'companies/fairDetails', $filenameToStore);
                $fair_detail = $path;
            }
        }else{
            $fair_detail = '';
        }

        $vid = $this->YoutubeID($data['intro_video']);
        EnrollReservation::where('id', $id)->update([
        'category_id' => $data['exhibition_category'],
        'company_name' => $data['company_name'],
        'company_website' => $data['company_site'],
        'employer_id' => auth()->guard('employer')->user()->employers_id,
        'intro_video' => $vid,
        'description' => $data['description'],
        'start_date' => $data['exhibition_date'],
        'end_date' => $data['to_date'],
        'payment_status' => '0',
        'fair_detail' => $fair_detail,
        ]);


        return redirect('employer/enroll/all-detail')->with('message', 'Enroll Updated Successfully');


    }

    public function deleteEnroll($res_id = null)
    {
        BoothReserve::where('reservation_id', $res_id)->delete();
        EnrollPhoto::where('reservation_id',$res_id)->delete();
        EnrollVideo::where('reservation_id', $res_id)->delete();
        EnrollBanner::where('reservation_id', $res_id)->delete();
        EnrollReservation::find($res_id)->delete();
    //    EnrollReservation::where('id', $res_id)
    //         ->with('boothreserves')
    //         ->with('photos')
    //         ->with('videos')
    //         ->delete();
        return redirect()->back();
    }
    public function paymentDetail()
    {
        $reserves = BoothReserve::where('employer_id', auth()->guard('employer')->user()->employers_id)->where('status', 0)->get();
        $total_reservation = EnrollReservation::get()->count();
        return view('employer.enroll.payment_detail', compact('reserves', 'total_reservation'));
    }

    public function getParticipateUsers(Request $request)
    {
        $data['contacts'] = [];
        // $contacts =\App\EmployeeRegistration::get(); Must get data from registeration
        $contacts = \App\UserCircle::where('status', 1)->get();
        foreach ($contacts as $key => $contact) {
          $number_of = false;
          $chk_msg = \App\ChatMessage::where('message_from', $contact->staff_id)->where('message_to', auth()->guard('employer')->user()->employers_id )->where('view_status','!=', '1')->count();
          if ($chk_msg > 0) {
            $number_of = $chk_msg;
          }

          $data['contacts'][] = [
            'id'    => $contact->staff_id,
            'name'  => Employees::getName($contact->staff_id),
            'image'  => Employees::getPhoto($contact->staff_id),
            'status'  => Employees::CheckOnline($contact->staff_id),
            'number_of' => $number_of,

          ];
        }

      $return_data = view('employer.enroll.messages.message_users')->with('data',$data)->render();
      return response()->json($return_data);
    }

    public function GetChatBox($user_id='', Request $request)
    {

        $page = 0;
        if ($request->page) {
            $page= $request->page;
        }
        $limit = 20;
        $start = $page * $limit;
        $data = [];

        $my_id = auth()->guard('employer')->user()->employers_id;
        // Make read all unread message
        \App\ChatMessage::where(['message_from' => $user_id, 'message_to' => $my_id])->update(['view_status' => 1]);

        // Get all message message_from selected user
        $messages = \App\ChatMessage::where(function ($query) use ($user_id, $my_id) {
            $query->where('message_from', $user_id)->where('message_to', $my_id)->where('to_delete', '!=', '1');
        })->oRwhere(function ($query) use ($user_id, $my_id) {
            $query->where('message_from', $my_id)->where('message_to', $user_id)->where('from_delete', '!=', '1');
        })->orderBy('id','desc');


        $data['message'] = $messages->skip($start)->take($limit)->get()->reverse();
        $totmsg = $messages->count();
        $fetmsg = ($page + 1) * $limit;
        $data['ldmr'] = 1;
        if ($totmsg > $fetmsg) {
          $data['ldmr'] = 2;
        }
        $data['user_id'] = $user_id;
        $data['name'] = Employees::getName($user_id);
        $data['image'] = Employees::getPhoto($user_id);
        $data['page'] = $page + 1;
        if ($page > 0) {
          $return_data['data'] = view('employer.enroll.messages.chats')->with('data',$data)->render();
          $return_data['ldmr'] = $data['ldmr'];
        } else{
          $return_data = view('employer.enroll.messages.chat_box')->with('data',$data)->render();
        }

        return response()->json($return_data);
    }
    public function sendMessage(Request $request)
    {
        $json = [];

        $this->validate($request,[
            'receiver_id' => 'required|integer',
            'message'   => 'required'
        ]);

        $from = auth()->guard('employer')->user()->employers_id;
        $sender_name = \App\Employers::getName($from);

        $to = $request->receiver_id;
        $message = $request->message;

        $data = new \App\ChatMessage();
        $data->message_from = $from;
        $data->message_to = $to;
        $data->message = $message;
        $data->view_status = 0; // message will be unread when sending message
        $data->from_delete = 0;
        $data->to_delete = 0;
        $data->save();

        // pusher
        $options = array(
            'cluster' => 'ap2',
            'useTLS' => true
        );

        $pusher = new Pusher(
            env('PUSHER_APP_KEY'),
            env('PUSHER_APP_SECRET'),
            env('PUSHER_APP_ID'),
            $options
        );

        $html = '<li id="chat_'.$data->id.'" class="p-1 rounded mb-1">
                                <div class="receive-msg">
                                    <div class="receive-msg-img">
                                    <img src="'.asset(\App\Employers::getEmpLogo($data->message_from)).'">
                                    </div>
                                    <div class="receive-msg-desc rounded text-center mt-1 ml-1 pl-2 pr-2">
                                        <p class="mb-0 mt-1 pl-2 pr-2 rounded">'.$data->message.'</p>

                                    </div>
                                </div>
                                <i id="delete_'.$data->id.'" class="fa fa-remove delete_chat"></i>
                            </li>';

        $pda = ['from' => $from, 'to' => $to, 'html' => $html, 'sender_name' => $sender_name ]; // sending from and to user id when pressed enter

        $pusher->trigger('my-channel', 'my-event', $pda);

        $json['data'] = '<li id="chat_'.$data->id.'" class="pl-2 pr-2 rounded text-white text-center send-msg mb-1 unread_message">'.$data->message.'<i id="delete_'.$data->id.'" class="fa fa-remove delete_chat"></i></li>';

        return response()->json($json);

    }

    public function deleteBooth($id=null)
    {
        BoothReserve::where('id', $id)->delete();
        return redirect()->back();
    }
    public function getBoothType($id=null )
    {
        $ticket_type['data'] = BoothTicketType::where('booth_id', $id)->get();
        echo json_encode($ticket_type);

    }

    public function getBoothPrice($id=null)
    {
        $ticket_price['data'] = BoothTicketType::where('id',$id)->first();
        echo json_encode($ticket_price);
    }

    public function dashboard()
    {

        $employer = \App\Employers::where('id', auth()->guard('employer')->user()->employers_id)->first();

        $pr = 0;
        if($employer->org_type != 0)
        {
            $pr += 1;
        }
        if($employer->ownership != 0)
        {
            $pr += 1;
        }
        if($employer->logo != '')
        {
            $pr += 1;
        }
        if($employer->banner != '')
        {
            $pr += 1;
        }
        if($employer->description != '')
        {
            $pr += 1;
        }
        if($employer->approval != 0)
        {
            $pr += 1;
        }

        $address = $employer->EmployerAddress;
        $head = $employer->EmployerHead;
        $contactperson = $employer->EmployerContactPerson;
        if($address->phone != '')
        {
            $pr += 1;
        }

        if($address->secondary_email != '')
        {
            $pr += 1;
        }
        if($address->fax != '')
        {
            $pr += 1;
        }
        if($address->pobox != '')
        {
            $pr += 1;
        }
        if($address->website != '')
        {
            $pr += 1;
        }
        if($address->address != '')
        {
            $pr += 1;
        }
        if($address->country != '')
        {
            $pr += 1;
        }
        if($address->city != '')
        {
            $pr += 1;
        }
        if($address->billing_address != '')
        {
            $pr += 1;
        }

        if($contactperson->phone != ''){
            $pr += 1;
        }
        if($contactperson->name != ''){
            $pr += 1;
        }
        if($contactperson->designation != ''){
            $pr += 1;
        }
        if($contactperson->email != ''){
            $pr += 1;
        }
        if($head->name != ''){
            $pr += 1;
        }
        if($head->designation != ''){
            $pr += 1;
        }
        $employer_id = $employer->id;
        $percent = ($pr / 21) * 100;
        $datas['profile_complete'] = number_format($percent);
        $today = Carbon::now()->toDateString();

        $datas['enroll'] = EnrollReservation::where('employer_id', auth()->guard('employer')->user()->employers_id)
        ->with('boothreserves')
        ->with('viewers')
        ->orderBy('created_at', 'desc')
        ->paginate(50);
        // dd($datas);
        $datas['participator']= 0;
        $datas['total_booth'] = 0;
        $datas['pending'] = 0;
        $datas['amount'] = 0;
        foreach ($datas['enroll'] as $key => $val)
        {
            foreach($val['viewers'] as $viewer)
            {
                $datas['participator'] += $viewer->count();
            }
            foreach($val['boothreserves'] as $booth)
            {
                if($booth->status == 0)
                {
                    $datas['pending'] += 1;
                }
            }

            $datas['amount'] += $val->total_price;
            $datas['total_booth'] += $val['boothreserves']->count();
        }
        $datas['active'] = EnrollReservation::where('employer_id', auth()->guard('employer')->user()->employers_id)->where('publish_status', 1)
                            ->with('boothreserves')
                            ->orderBy('created_at', 'desc')
                            ->with('viewers')
                            ->paginate(50);

        $datas['inactive'] = EnrollReservation::where('employer_id', auth()->guard('employer')->user()->employers_id)->where('publish_status', 0)
                            ->with('boothreserves')
                            ->with('viewers')
                            ->orderBy('created_at', 'desc')
                            ->paginate(50);


        return view('employer.enroll.dashboard_new', compact('datas'));
    }


    //Agora
    public function checkLivestreamPlatform($slug = null)
    {

        $data['enroll'] = EnrollReservation::where('seo_url', $slug)->first();
        if($data['enroll']->platform == "agora")
        {
            $data = EnrollSettings::livestream($data['enroll']->seo_url);
            return view('employer.enroll.broadcast_live', compact('data'));
        }else{
            return redirect()->back();
        }
        // $data['enroll'] = EnrollReservation::where('seo_url', $slug)->first();
        // $data['channel'] = $slug;
    }

    public function storeStartTime(Request $request){
        EnrollEmployerStream::where('channel', $request['channel'])->update([
            'start_time' => now()
        ]);
        return 'Start Time';
    }

    public function storeEndTime(Request $request){
        EnrollEmployerStream::where('channel', $request['channel'])->update([
            'end_time' => now()
        ]);

        return 'End Time';
    }

    public function checkVideoCallPlatform($channel=null)
    {

        $data['enroll'] = EnrollReservation::where('seo_url', $channel)->first();
        if($data['enroll']->platform == "agora")
        {
            $datas = EnrollSettings::videoCall($channel);
            return view('employer.enroll.videocall', compact('datas'));
        }else{
            return redirect()->back();
        }

    }

    public function saveVideoCallChannel(Request $request)
    {

        $data['group_channel'] = EnrollGroupVideoChannel::where('reservation_id', $request->reservation_id)->first();
        if($data['group_channel'] != null){
            return;
        }else{
            EnrollGroupVideoChannel::create([
                'reservation_id' => $request->reservation_id,
                'available_channel' => $request->channel,
                'start_time' => now()
            ]);
        }
    }

    public function deleteVideoCallChannel(Request $request)
    {
        EnrollEmployerStream::where('channel', $request->channel)->update([
            'end_time' => now()
        ]);
        EnrollGroupVideoChannel::where('available_channel', $request->channel)->delete();

    }


    //Zomm api
    public function zoomVideoCall($slug=null)
    {


        $data = EnrollReservation::where('seo_url', $slug)->first();
        // $token = $this->access();
        $token=$this->getZoomToken();
        if ($data) {
            //        zoom api
            $curl = curl_init();
            $body = json_encode(array(
                "created_at"=> "2019-09-05T16:54:14Z",
                    "duration"=> 60,
                    "host_id"=> "AbcDefGHi",
                    "id"=> 1100000,
                    "join_url"=> "https://zoom.us/j/1100000",
                    "settings"=> array(
                        "alternative_hosts"=> "",
                        "approval_type"=> 2,
                        "audio"=> "both",
                        "auto_recording"=> "local",
                        "close_registration"=> false,
                        "cn_meeting"=> false,
                        "enforce_login"=> false,
                        "enforce_login_domains"=> "",
                        // "global_dial_in_countries"=> [
                        //     "Nepal"
                        // ],
                        // "global_dial_in_numbers"=> [
                        //     array(
                        //         "city"=> "Kathmandu",
                        //         "country"=> "Nepal",
                        //         "country_name"=> "Nepal",
                        //         "number"=> "+1 1000200200",
                        //         "type"=> "toll"
                        //     ),
                        //     array(
                        //         "city"=> "San Jose",
                        //         "country"=> "US",
                        //         "country_name"=> "US",
                        //         "number"=> "+1 6699006833",
                        //         "type"=> "toll"
                        //     ),
                        //     array(
                        //         "city"=> "San Jose",
                        //         "country"=> "US",
                        //         "country_name"=> "US",
                        //         "number"=> "+1 408000000",
                        //         "type"=> "toll"
                        //     )
                        // ],
                        "host_video"=> false,
                        "in_meeting"=> false,
                        "join_before_host"=> true,
                        "mute_upon_entry"=> false,
                        "participant_video"=> false,
                        "registrants_confirmation_email"=> true,
                        "use_pmi"=> false,
                        "waiting_room"=> false,
                        "watermark"=> false,
                        "registrants_email_notification"=> true
                    ),
                    "start_time"=> "2019-08-30T22:00:00Z",
                    "start_url"=> "https://zoom.us/s/1100000?iIifQ.wfY2ldlb82SWo3TsR77lBiJjR53TNeFUiKbLyCvZZjw",
                    "status"=> "waiting",
                    "timezone"=> "Asia/Kathmandu",
                    "topic"=> "API Test",
                    "type"=> 2,
                    "uuid"=> "ng1MzyWNQaObxcf3+Gfm6A=="
            ), true);
            curl_setopt_array($curl, array(
                CURLOPT_HTTPHEADER => array(
                    "authorization: Bearer $token",
                    "content-type: application/json"
                ),
                CURLOPT_URL => "https://api.zoom.us/v2/users/ganga.sinjalimagar@gmail.com/meetings",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,

            ));
            $response = curl_exec($curl);
            $err = curl_error($curl);


            if ($err) {
                echo "cURL Error #:" . $err;
            } else {
                $resp = json_decode($response);
                //
                $assistant=BoothAssistantZoom::create([

                    'reservation_id' => $data->id,
                    'created_at' => now(),
                    'meeting_id' => $resp->id,
                    'password' => $resp->password,
                    'url'=> $resp->join_url
                ]);

                if ($assistant) {
                    \Session::flash('alert-success', 'You Successfully invite application for zoom meeting with id: '.$resp->id);
                    // $this->zoomsdk($resp->id);
                    return redirect($resp->join_url);
                } else {
                    \Session::flash('alert-danger', 'Something Went Wrong on Saving Data');
                    return redirect('employer/dashboard/enroll');
                }
            }
        } else {

            \Session::flash('alert-danger','You choosed wrong Data');
            return redirect()->back();
        }


    }


    protected function getZoomToken()
    {
    //        get refresh_token from db

        $refresh_token=DB::table('zoom_api')->where('id', '=', 1)->first()->refresh_token;
        $token_url="https://zoom.us/oauth/token?grant_type=refresh_token&refresh_token=".$refresh_token;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => array(
                "Authorization: Basic YmdQQmxmMFBTeGV2bERtTlJnMGowQTpveENQcWh2QllsbHlSVWpkdlY1ZHBuc2wyd2tCanFNVg==",
            ),
            CURLOPT_URL => $token_url,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CUSTOMREQUEST => "POST",
        ));

        $data = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($data);
        DB::table('zoom_api')->where('id', '=', 1)->update([
            'refresh_token' => $result->refresh_token,
        ]);

        return $result->access_token;
    }

    public function zoomsdk($meetingId = null)
    {
        // generate_signature
        $api_key = 'JIv5J1mEQl2Qbozx6e9rYQ';
        $api_secret = 'H7I5TrGGalL9Piiflua0QWk1n0aK0FoNI9zi';
        $meeting_number = $meetingId;
        $role = 1;


        $time = time() * 1000 - 30000;//time in milliseconds (or close enough)

        $data = base64_encode($api_key . $meeting_number . $time . $role);

        $hash = hash_hmac('sha256', $data, $api_secret, true);

        $_sig = $api_key . "." . $meeting_number . "." . $time . "." . $role . "." . base64_encode($hash);

        //return signature, url safe base64 encoded
        $signature =  rtrim(strtr(base64_encode($_sig), '+/', '-_'), '=');
        // zoom details
        $zoom_meting_id = $meeting_number;
        $username = 'Ganga magar';
        $zoom_password = 'e7DRolXBVn';
        // role  -->  0=>Attendee,1=>Host,5=>Assistant
        $data = json_encode(array(
            'apiKey' => "JIv5J1mEQl2Qbozx6e9rYQ",
            'china' => "0",
            'email' => "ganga.sinjalimagar@gmail.com",
            'lang' => "en-US",
            'mn' => $zoom_meting_id,
            'name' => $username,
            'pwd' => $zoom_password,
            'role' => "1",
            'signature' =>$signature,
        ),true);
        dd($data);
        return view('employer.enroll.zoomhost', compact('data'));
    }
    public function access()
    {
        // dd($request->input('code'));
        //access_token = eyJhbGciOiJIUzUxMiIsInYiOiIyLjAiLCJraWQiOiI1MGJhNzYwYS04NWZjLTQ3MGEtYmMxMC01YmUyNWI4ZGI3ZTEifQ.eyJ2ZXIiOjcsImF1aWQiOiJjODJjODQ0NmQ1OTUzOTE2MGFhNDdkNDgzMDc0NDhiNCIsImNvZGUiOiJrQ3RQVzA0T0FLX3FKQXFES3dlVGx5WTJoeDBuMFNtdnciLCJpc3MiOiJ6bTpjaWQ6YmdQQmxmMFBTeGV2bERtTlJnMGowQSIsImdubyI6MCwidHlwZSI6MCwidGlkIjowLCJhdWQiOiJodHRwczovL29hdXRoLnpvb20udXMiLCJ1aWQiOiJxSkFxREt3ZVRseVkyaHgwbjBTbXZ3IiwibmJmIjoxNTk5MDI3MDQzLCJleHAiOjE1OTkwMzA2NDMsImlhdCI6MTU5OTAyNzA0MywiYWlkIjoidkhpUU9JRGVReFNtTnI0OVZfY1Q4ZyIsImp0aSI6IjI5MGEwMmI3LTU5ODAtNDZhMi1hNGY3LWQ0MWViNWVmN2YzZiJ9.26Ay1BsbWJlX_ka_b8aBQi6BEn2JjXWGG1INrE4q_SiamlEx5Uyu6pSfQkVzYkqrLusV0ZONus_Xt4kN4NK09A
        $token_url="https://zoom.us/oauth/token?grant_type=authorization_code&code=uCEKPuRxjk_qJAqDKweTlyY2hx0n0Smvw&redirect_uri=http://127.0.0.1:8000/access";
        // dd($token_url);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => array(
                "authorization: Basic YmdQQmxmMFBTeGV2bERtTlJnMGowQTpveENQcWh2QllsbHlSVWpkdlY1ZHBuc2wyd2tCanFNVg==",
                "content-type: application/json"
            ),
            CURLOPT_URL => $token_url,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            // CURLOPT_URL => $token_url,
            // CURLOPT_SSL_VERIFYPEER => true,
            // CURLOPT_RETURNTRANSFER => true,
            // CURLOPT_POST => true,
            // CURLOPT_POSTFIELDS => $content

        ));

        $data = curl_exec($curl);
        curl_close($curl);
        $result = json_decode($data);

        // dd("hellp".$result);

        // $access_token = $result->access_token;
        dd($result);
    }

    public function authcall()
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://zoom.us/oauth/authorize?response_type=code&client_id=bgPBlf0PSxevlDmNRg0j0A&redirect_uri=http://127.0.0.1:8000/access",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        dd($response);
    }


}
