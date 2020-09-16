<?php

namespace App\Http\Controllers\employer\payments;

use App\Http\Controllers\Controller;
use App\library\Settings;
use Illuminate\Http\Request;
use App\EnrollInvoice;
use App\EnrollInvoiceHistory;
use App\EnrollInvoiceItem;
use App\Employers;
use App\IndividualEnrollCart;
use DB;
use Mail;



class KhaltiController extends Controller
{
    public function index()
    {
        $opt = \App\Payments::where('payment_page', 'Khalti')->first();
        $setting = json_decode($opt->setting);
        $data['amount'] = session()->get('total_amount');
        $data['productIdentity'] = session()->get('cart_id');
        $data['productName'] = "Enroll";
        $data['productUrl'] = \URL::to('/')."/employer/enroll/detail";
		$data['publicKey'] = $setting->public_key;
        return view('employer.payments.khalti')->with('data', $data);
    }

    public function verify(Request $request)
	{
		DB::beginTransaction();
		try {

			$opt = \App\Payments::where('payment_page', 'Khalti')->first();
			$setting = json_decode($opt->setting);
			$args = http_build_query(array(
				'token' => $request['token'],
				'amount'  => session()->get('total_amount')*100
			));

			$url = "https://khalti.com/api/v2/payment/verify/";

			# Make the call using API.
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS,$args);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

			$headers = ['Authorization: Key '.$setting->secret_key];
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			// Response
			$response = curl_exec($ch);
			$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);

			$total = EnrollInvoice::where('created_at', 'LIKE', date('Y').'%')->count();
			$new_number = $total + 1;
			$invoice_no = 'INV-'.date('Y').'-'.$new_number;
            $employer = \App\Employers::where('id',auth()->guard('employer')->user()->employers_id)->first();
            if($request->comment)
            {
                $comment = $request->comment;
            }
            else{
                $comment = '';
            }
			$invoice = EnrollInvoice::create([
				'invoice_by' => $employer->id,
				'invoice_no' => $invoice_no,
				'company_name' => $employer->name,
				'email' =>  $employer->email,
				'comment' => $comment,
				'amount' => Session()->get('total_amount'),
				'invoice_status' => 'Completed',
				'payment_type' => 'Khalti'
			]);
			if(isset($invoice->id)){
				$job_ids = Session()->get('job_id');
				foreach ($job_ids as $key => $job) {
					$cart = IndividualEnrollCart::where('id',$job)->first();
					EnrollInvoiceItem::create([
						'invoice_id' => $invoice->id,
						'category' => $cart->reservations->category->title,
                        'booth_name' => $cart->boothreserve->booth_name,
                        'booth_type' => $cart->boothreserve->booth_type,
                        'type' => 'Enroll online-exhibition',
						'amount' => $cart->boothreserve->price
					]);
					\App\BoothReserve::where('id', $cart->booth_id)->update([
						'status' => 1
					]);
				}

				EnrollInvoiceHistory::create([
					'invoice_id' => $invoice->id,
					'invoice_status' => "Complete",
					'notify' => 1,
					'comment' => 'Invoice Placed Successfully',
				]);

				$mydata = array(
					'to_name' => $employer->name,
					'to_email' => $employer->email,
					'subject' => 'Invoice for the Enroll',
					'invoice_detail' => EnrollInvoice::where('id',$invoice->id)->first(),
					'from_name' => Settings::getSettings()->name,
					'logo' => Settings::getImages()->logo,
					'from_email' => Settings::getSettings()->email,
					'store_address' => Settings::getSettings()->address,
					'store_phone' => Settings::getSettings()->telephone,
				);


				Mail::send('mail.enroll_invoice', ['data' => $mydata], function($mail) use ($mydata){
					$mail->to($mydata['to_email'],$mydata['to_name'])->from($mydata['from_email'],$mydata['from_name'])->subject($mydata['subject']);
				});
			}


			Session()->forget('job_id');
			Session()->forget('cart_id');
			Session()->forget('total_amount');


            IndividualEnrollCart::where('employer_id', auth()->guard('employer')->user()->employers_id)->delete();
            // \Session::flash('alert-success','Record have been saved Successfully');

            DB::commit();

			return response()->json(['message'  => 'Record have been saved Successfully', "response" => $response]);
		} catch (\Exception $e) {
			DB::rollback();
			// \Session::flash('alert-danger', $e->getMessage());
			return response()->json(['message'  => $e->getMessage()], 400);
		}
	}
}

