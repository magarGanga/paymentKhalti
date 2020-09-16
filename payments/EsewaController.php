<?php

namespace App\Http\Controllers\employer\payments;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Payments;
use App\Order;
use App\OrderItem;
use App\OrderHistory;
use App\Employers;
use Mail;
use App\Imagetool;
use App\library\myFunctions;
use App\library\Settings;
use Carbon\Carbon;

class EsewaController extends Controller
{
     


    public function index()
    {
      $opt = \App\Payments::where('payment_page', 'Esewa')->first();
      $setting = json_decode($opt->setting);
      if ($setting->payment_mode == 2) {
      $data['action'] = 'https://esewa.com.np/epay/main';
      $data['payment_mode'] = 2;
        } else {
          $data['action'] = 'https://dev.esewa.com.np/epay/main';
           $data['payment_mode'] = 1;
        }

        $data['total_amount'] = session()->get('total_amount');
        $data['id'] = session()->get('cart_id');
        $data['scd'] = $setting->merchant_key;
        $data['su'] = url('employer/esewa/success');
        $data['fe'] = url('employer/cart');
       
        return view('employer.payments.esewa')->with('data', $data);

    }

    public function success(Request $request)
    {

     
        
       $order_id = 0;
       if(isset($request->oid)){
          $order_id= $request->oid;
        }
        if (Session()->has('cart_id') && Session()->has('total_amount') && Session()->has('job_id')) {
          if ($order_id == Session()->get('cart_id')) {

            $opt = \App\Payments::where('payment_page', 'Esewa')->first();
            $setting = json_decode($opt->setting);

            if ($setting->payment_mode == 2) {
            $esewa_verfication_url = 'https://esewa.com.np/epay/transrec';
            } else {
              $esewa_verfication_url = 'https://dev.esewa.com.np/epay/transrec';
            } 


            //create array of data to be posted
      $post_data['amt'] = Session()->get('total_amount');
      $post_data['scd'] = $setting->merchant_key;
      $post_data['pid'] = $order_id;
      $post_data['rid'] = $request->refId;

      //traverse array and prepare data for posting (key1=value1)
      foreach ($post_data as $key => $value) {
          $post_items[] = $key . '=' . $value;
      }

      //create the final string to be posted using implode()
      $post_string = implode('&', $post_items);

      //create cURL connection
      $curl_connection =  curl_init($esewa_verfication_url);

      //set options
      curl_setopt($curl_connection, CURLOPT_CONNECTTIMEOUT, 30);
      curl_setopt($curl_connection, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
      curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl_connection, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curl_connection, CURLOPT_FOLLOWLOCATION, 1);

      //set data to be posted
      curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $post_string);

      //perform our request
      $result = curl_exec($curl_connection);
      
      if(curl_error($curl_connection)) {
          echo 'error:('.curl_errno($curl_connection).')' . curl_error($curl_connection);
      } else {
          
      $verification_response  = strtoupper( trim( strip_tags( $result ) ) ) ;

      if('SUCCESS' == $verification_response){
       
        $total = Order::where('created_at', 'LIKE', date('Y').'%')->count();
        $new_number = $total + 1;
        $invoice_no = 'INV-'.date('Y').'-'.$new_number;
        $employer = Employers::where('id',auth()->guard('employer')->user()->employers_id)->first();
        $address = $employer->EmployerAddress;
        $order = Order::create([
          'order_by' => $employer->id, 
          'invoice_no' => $invoice_no, 
          'customer_name' => $employer->name,
          'email' => auth()->guard('employer')->user()->email,
          'telephone' => $address->phone,
          'comment' => '',
          'amount' => Session()->get('total_amount'),
          'order_status' => $setting->order_status,
          'payment_type' => 'Esewa'
        ]);

        if(isset($order->id)){
          $job_ids = Session()->get('job_id');
          foreach ($job_ids as $key => $job) {
            $type = '';
            $name = '';
            $cart = \App\Cart::where('id',$job)->first();
            if ($cart->type == 'Jobs') {
             \App\Jobs::where('id',$cart->jobs_id)->update(['status' => 1]);
             $type = 'Job';
             $name = \App\Jobs::getTitle($cart->jobs_id);
             $product_id = $cart->jobs_id;
            } elseif ($cart->type == 'Tenders') {
              \App\Tender::where('id',$cart->jobs_id)->update(['status' => 1]);
               $type = 'Tender';
              $name = \App\Tender::getTitle($cart->jobs_id);
              $product_id = $cart->jobs_id;
            }
            elseif ($cart->type == 'JobPackage') {
              $expiry_date = Carbon::now()->addDays(365)->format('Y-m-d');
              $empp = \App\EmployerPackage::create([
                'order_id'      => $order->id,
                'employers_id'  => $cart->employers_id, 
                'job_type'      => $cart->job_type_id, 
                'job_number'    => $cart->number_of, 
                'remaining'     => $cart->number_of, 
                'purchase_date' => date('Y-m-d'), 
                'expiry_date'   => $expiry_date,
                'duration'      => $cart->duration,
                'status'        => 1,
                'type'          => 'Job'
              ]);
               $type = 'JobPackage';
                $name = 'Job Package('.$cart->number_of.')';
                $product_id = $empp->id;
            }

             elseif ($cart->type == 'ResumePackage') {
              $expiry_date = Carbon::now()->addDays($cart->duration)->format('Y-m-d');
              $empp = \App\EmployerPackage::create([
                'order_id'      => $order->id,
                'employers_id'  => $cart->employers_id, 
                'job_type'      => $cart->job_type_id, 
                'job_number'    => $cart->number_of, 
                'remaining'     => $cart->number_of, 
                'purchase_date' => date('Y-m-d'), 
                'expiry_date'   => $expiry_date,
                'duration'      => $cart->duration,
                'status'        => 1,
                'type'          => 'Resume'
              ]);
               $type = 'ResumePackage';
                $name = 'Resume Package('.$cart->number_of.')';
                $product_id = $empp->id;
            }

            elseif ($cart->type == 'MemberUpgrade') {
              $expiry_date = Carbon::now()->addMonths($cart->duration)->format('Y-m-d');
               Employers::where('id',$cart->employers_id)->update(['member_type' => $cart->job_type_id]);
           
                $empp = \App\UpgradeRequest::create([
                  'employers_id' => $cart->employers_id,
                  'member_type_id' => $cart->job_type_id,
                    'status' => 1,
                    'start_date' => date('Y-m-d'),
                    'end_date' => $expiry_date
                ]);
               $type = 'MemberUpgrade';
                $name = 'Member Upgrade('.$cart->job_type.')';
                $product_id = $empp->id;
            }
            
            elseif ($cart->type == 'TenderPackage') {
              $expiry_date = Carbon::now()->addDays(365)->format('Y-m-d');
              $empp = \App\EmployerPackage::create([
                'order_id'      => $order->id,
                'employers_id'  => $cart->employers_id, 
                'job_type'      => $cart->job_type_id, 
                'job_number'    => $cart->number_of, 
                'remaining'     => $cart->number_of, 
                'purchase_date' => date('Y-m-d'), 
                'expiry_date'   => $expiry_date,
                'duration'      => $cart->duration,
                'status'        => 1,
                'type'          => 'Tender'
              ]);
               $type = 'TenderPackage';
                $name = 'Tender Package('.$cart->number_of.')';
                $product_id = $empp->id;
            }
            
            OrderItem::create([
              'order_id' => $order->id, 
              'product_id' => $product_id, 
              'product_type' => $cart->job_type,
              'name' => $name,
              'type' => $type,
              'duration' => $cart->duration,
              'amount' => $cart->amount
            ]);
          }

           OrderHistory::create([
              'order_id' => $order->id, 
              'order_status' => $setting->order_status, 
              'notify' => 1,
              'comment' => 'Order Placed Successfully'
            ]);

           $mydata = array(
                    'to_name' => $employer->name, 
                    'to_email' => auth()->guard('employer')->user()->email,
                    'subject' => 'Order Invoice',
                    'order_detail' => Order::where('id',$order->id)->first(),
                    
                    'from_name' => Settings::getSettings()->name,
                    'logo' => Settings::getImages()->logo,
                    'from_email' => Settings::getSettings()->email,

                     'store_address' => Settings::getSettings()->address,
                     'store_phone' => Settings::getSettings()->telephone,
                   
                                      
                    );
        set_time_limit(600);
         myFunctions::setEmail();
        Mail::send('mail.job_order', ['data' => $mydata], function($mail) use ($mydata){
                  $mail->to($mydata['to_email'],$mydata['to_name'])->from($mydata['from_email'],$mydata['from_name'])->subject($mydata['subject']);
                });
        }


          Session()->forget('job_id');
          Session()->forget('cart_id');
          Session()->forget('total_amount');
          \App\Cart::where('employers_id', auth()->guard('employer')->user()->employers_id)->delete();
          \Session::flash('alert-success','Record have been saved Successfully');
        return redirect('employer/order');

      }
      else{
          \Session::flash('alert-danger','Payment not success');
          return redirect('/employer/cart');
      }
      }
      //close the connection
      curl_close($curl_connection); 



            # code...
          } else{
            \Session::flash('alert-danger','Cart id did not match');
            return redirect('employer/order');
          }
        } else{
          \Session::flash('alert-danger','session not found');
          return redirect('employer/order');
        }

        
   

    }

     
     
  
   

}
