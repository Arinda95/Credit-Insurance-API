<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Admin;
use App\Session;
use App\TestEmail;
Use App\TestSms;
Use App\Wallet;
Use App\Token;
use App\Notification;
Use App\SessionRecord;
Use App\Rate;
Use App\Business;
Use App\Customer;
Use App\Transaction;
Use App\TransactionsData;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    protected $akellobankeremail;
    protected $akellobankernumber;

    public function __construct()
    {
        $this->akellobankeremail = 'AkelloBanker CreditCloud Email';
        $this->akellobankernumber = 'AkelloBanker CreditCloud Phonenumber';
    }

    public function sendemail($emailpack){
        //unload email pack
        $emaildata = json_decode($emailpack);

        $email = new TestEmail;
        $email->from_email = $this->akellobankeremail;
        $email->to_email = $emaildata->to_email;
        $email->title = $emaildata->title;
        $email->message = $emaildata->message;
        $email->save();
    }

    public function sendsms($smspack){
        //unload sms pack
        $smsdata = json_decode($smspack);

        $sms = new TestSms;
        $sms->from_number = $this->akellobankernumber;
        $sms->to_number = $smsdata->to_phonenumber;
        $sms->title = $smsdata->title;
        $sms->message = $smsdata->message;
        $sms->save();
    }

    public function statusverify(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $status = (Admin::where('admin_id', $admin_id)->first())->status;
        return $status;

    }

    public function getlevel(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $level = (Admin::where('admin_id', $admin_id)->first())->level;
        return $level;

    }

    public function tokenverify(Request $request) {
        $req = $request->input('body');
        $input_token = $req['token'];

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];


        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $target_token = (Token::where('user_id', $admin_id)->first())->token_hash;

        if(password_verify($input_token, $target_token))
        {
            $verified_admin = Admin::where('admin_id', $admin_id)->first();
            $verified_admin->status = "verified";
            $verified_admin->save();

            $target_token_del = Token::where('user_id', $admin_id)->first();
            $target_token_del->delete();

            $content = 'Verified';
            $status = 200;
            return response($content, $status);
        }
        else{
            $content = 'InvalidToken';
            $status = 200;
            return response($content, $status);
        }
    }
    
    public function searchcust(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $customer_phonenumber = $body['customer_phonenumber'];

        $customer = Customer::where('phonenumber', $customer_phonenumber)->first();

        return $customer;

    }

    public function searchbiz(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $business_phonenumber = $body['business_phonenumber'];

        $business = Business::where('phonenumber', $business_phonenumber)->first();

        return $business;

    }

    public function transactionsdivebusiness(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $business_id = $body['business_id'];

        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['part_1' => $business_id];
        $transactions = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(10000);

        foreach($transactions as $transaction){

            $transaction->transactions_id = $transaction->transactions_id;
            $transaction->type = $transaction->type;
            $customer = Customer::where('customer_id', $transaction->part_2)->first();
            $names = "".$customer->fname." ".$customer->lname."";
            $transaction->phonenumber = $customer->phonenumber;
            $transaction->names = $names;
            
            $transaction->state = $transaction->state;
            $transaction->due_by = date("F jS, Y", strtotime($transaction->due_by));
            $transaction->total = $transaction->total;
            $transaction->insured = $transaction->insured;
            $transaction->date = date("F jS, Y", strtotime($transaction->created_at));
            $transaction->transactions_data = TransactionsData::where('data_id', $transaction->transactions_data_id)->get();

            $transaction->makeHidden('part_1');
            $transaction->makeHidden('part_2');
            $transaction->makeHidden('created_at');
        }

        return $transactions;

    }

    public function transactionsdivecustomer(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $customer_id = $body['customer_id'];

        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['part_2' => $customer_id];
        $transactions = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(10000);

        foreach($transactions as $transaction){

            $transaction->transactions_id = $transaction->transactions_id;
            $transaction->type = $transaction->type;
            $transaction->business = (Business::where('business_id', $transaction->part_1)->first())->name;
            $transaction->phonenumber = (Business::where('business_id', $transaction->part_1)->first())->phonenumber;
            $transaction->state = $transaction->state;
            $transaction->due_by = $transaction->due_by;
            $transaction->total = $transaction->total;
            $transaction->insured = $transaction->insured;
            $transaction->transactions_data = TransactionsData::where('data_id', $transaction->transactions_data_id)->get();
            $transaction->date = date("F jS, Y", strtotime($transaction->created_at));
        }

        return $transactions;

    }

    public function fetchadminbynumber(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $phonenumber = $body['phonenumber'];

        $admin = Admin::where('phonenumber', $phonenumber)->first();

        return $admin;

    }

    public function fetchbusinessbynumber(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $phonenumber = $body['phonenumber'];

        $business = Business::where('phonenumber', $phonenumber)->first();

        return $business;

    }

    public function fetchcustomerbynumber(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $phonenumber = $body['phonenumber'];

        $customer = Customer::where('phonenumber', $phonenumber)->first();

        return $customer;

    }

    public function updatecustomerlevel(Request $request) {
        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $customer_id = $body['customer_id'];
        $level = $body['level'];
        $input_password = $body['password'];

        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['admin_id' => $admin_id, 'level' => 3];
        $admin_pass_token = (Admin::where($search)->first())->password;

        if(password_verify($input_password, $admin_pass_token)){
            $customer = Customer::where('customer_id', $customer_id)->first();
            $customer->status = $level;
            $customer->save();

            $content = 'Customer Access Level Updated';
            $status = 200;
            return response($content, $status);
        }
        else{
            $content = 'Invalid Admin Password';
            $status = 200;
            return response($content, $status);
        }

    }

    public function updatebusinesslevel(Request $request) {
        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $business_id = $body['business_id'];
        $level = $body['level'];
        $input_password = $body['password'];

        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['admin_id' => $admin_id, 'level' => 3];
        $admin_pass_token = (Admin::where($search)->first())->password;

        if(password_verify($input_password, $admin_pass_token)){
            $admin = Business::where('business_id', $business_id)->first();
            $admin->status = $level;
            $admin->save();

            $content = 'Business Access Level Updated';
            $status = 200;
            return response($content, $status);
        }
        else{
            $content = 'Invalid Admin Password';
            $status = 200;
            return response($content, $status);
        }

    }

    public function updatetransactionrate(Request $request) {


        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $password = $body['password'];
        $new_tr_rate = $body['new_tr_rate'];

        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['admin_id' => $admin_id, 'level' => 3];
        $admin_pass_token = (Admin::where($search)->first())->password;

        if(password_verify($password, $admin_pass_token)){
            $tr_rate = Rate::where('rate_name', 'abtrrate')->first();
            $tr_rate->percentage_rate = $new_tr_rate;
            $tr_rate->save();

            $content = 'Transaction Rate Updated';
            $status = 200;
            return response($content, $status);
        }
        else{
            $content = 'Invalid Admin Password';
            $status = 200;
            return response($content, $status);
        }

    }

    public function getpolicyrate(Request $request) {
        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $rate = (Rate::where('rate_name', 'abinsrate')->first())->percentage_rate;
        return $rate;
    }

    public function gettransactionrate(Request $request) {
        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $rate = (Rate::where('rate_name', 'abtrrate')->first())->percentage_rate;
        return $rate;
    }

    public function updatepolicyrate(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $input_password = $body['password'];
        $new_policy_rate = $body['new_policy_rate'];

        $admin_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['admin_id' => $admin_id, 'level' => 3];
        $admin_pass_token = (Admin::where($search)->first())->password;

        if(password_verify($input_password, $admin_pass_token)){
            $policy_rate = Rate::where('rate_name', 'abinsrate')->first();
            $policy_rate->percentage_rate = $new_policy_rate;
            $policy_rate->save();

            $content = 'Policy Rate Updated';
            $status = 200;
            return response($content, $status);
        }
        else{
            $content = 'Invalid Admin Password';
            $status = 200;
            return response($content, $status);
        }

    }

    public function updatelevel(Request $request) {
        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $admin_id = $body['admin_id'];
        $level = $body['level'];
        $input_password = $body['password'];

        $admin_id_2 = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['admin_id' => $admin_id_2, 'level' => 3];
        $admin_pass_token = (Admin::where($search)->first())->password;

        if(password_verify($input_password, $admin_pass_token)){
            $admin = Admin::where('admin_id', $admin_id)->first();
            $admin->level = $level;
            $admin->save();

            $content = 'Access Level Updated';
            $status = 200;
            return response($content, $status);
        }
        else{
            $content = 'Invalid Admin Password';
            $status = 200;
            return response($content, $status);
        }

    }

    public function logout(Request $request) {
        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $delete = Session::where('session_id', $session_id)->first();

        //fetching current records
        $record = new SessionRecord;
        $record->session_id = $delete->session_id;
        $record->user_id = $delete->user_id;
        $record->client = $delete->client;
        $record->started_at = $delete->created_at;
        $record->updated_at = date("Y-m-d H:i:s");
        $record->save();

        $delete->delete();
        $content = 'LoggedOut';
        $status = 200;
        return response($content, $status);
    }

}
