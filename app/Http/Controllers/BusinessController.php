<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Business;
Use App\Customer;
use App\Session;
use App\TestEmail;
Use App\TestSms;
Use App\Wallet;
Use App\Token;
use App\Notification;
Use App\SessionRecord;
Use App\Transaction;
Use App\TransactionsData;
Use App\Rate;
Use App\TestMM;

class BusinessController extends Controller
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

    public function sendnotification($notificationpack){
        //unload notification pack
        $notif = json_decode($notificationpack);

        $notification = new Notification;
        $notification->notification_id = uniqid();
        $notification->recipient_id = $notif->recipient_id;
        $notification->type = $notif->type;
        $notification->title = $notif->title;
        $notification->body = $notif->message;
        $notification->post_script = $notif->post_script;
        $notification->read_state = 'unread';
        $notification->save();
    }

    public function mmphonenumbercheck($mm_number){

        //check if number is registered as a mobile money number
        try {
            $registered_number = TestMM::where('phonenumber', $mm_number)->first();
        }
        catch (\Throwable $e) {
            return false;            
        }
        return true;

    }

    public function checkmmamount($mm_number, $amount){

        //check if number has amount
        $mm_amount = (TestMM::where('phonenumber', $mm_number)->first())->amount;
        if ($mm_amount > $amount) {
            return true;
        }
        else if($amount > $mm_amount){
            return false;
        }

    }

    public function checkwalletamount($wallet_id, $amount){
        //check if recipient wallet has amount
        $wallet_balance = (Wallet::where('wallet_id', $wallet_id)->first())->balance;
        if ($wallet_balance > $amount) {
            return true;
        }
        else if($amount > $wallet_balance){
            return false;
        }
    }

    public function loadworkcash($cashpacket){
        $cash = json_decode($cashpacket);

        $phonenumber = $cash->phonenumber;
        $amount = $cash->amount;
        $mm_pin = $cash->mmpin;
        $wallet_id = $cash->wallet_id;

        //send cash to mm
        $mm_account_amount = (TestMM::where('phonenumber', $phonenumber)->first())->amount;
        $pin_hash = (TestMM::where('phonenumber', $phonenumber)->first())->pin;

        //verify pin
        if(password_verify($mm_pin, $pin_hash)){
            //pass
        }
        else{
            $content = 'Invalid pin';
            $status = 200;
            return response($content, $status);
            exit();
        }

        //reduce amount from mm account
        $mm_account = TestMM::where('phonenumber', $phonenumber)->first();
        $mm_account->amount = $mm_account_amount - $amount;
        $mm_account->save();

        //add cash to akellobanker
        $mm_account = TestMM::where('phonenumber', '0781077344')->first();
        $mm_account->amount = $mm_account_amount + $amount;
        $mm_account->save();

        $wallet_amount = (Wallet::where('wallet_id', $wallet_id)->first())->balance;
        $wallet = Wallet::where('wallet_id', $wallet_id)->first();
        $wallet->balance = $wallet_amount + $amount;
        $wallet->save();

        $content = 'Cash loaded';
        $status = 200;
        return response($content, $status);
    }

    public function statusverify(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $status = (Business::where('business_id', $business_id)->first())->status;
        return $status;

    }

    public function tokenverify(Request $request) {
        $req = $request->input('body');
        $input_token = $req['token'];

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];


        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $target_token = (Token::where('user_id', $business_id)->first())->token_hash;

        if(password_verify($input_token, $target_token))
        {
            $verified_business = Business::where('business_id', $business_id)->first();
            $verified_business->status = "verified";
            $verified_business->save();


            $notificationpack = json_encode(array(
                'recipient_id' => $business_id,
                'type' => 'Welcome',
                'title' => 'Welcome to the Akellobanker family',
                'message' => 'Welcome to the akellobanker creditfamily. We aim to provide reliable credit and support services for businesses and shoppers. Please read through our community guidelines and contact us through our support phonenumbers if you have any questions',
                'post_script' => 'Akellobanker Credit cloud is under Akellobanker. All rights reserved.'
            ));
            $this->sendnotification($notificationpack);

            $target_token_del = Token::where('user_id', $business_id)->first();
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

    public function sell(Request $request) {
        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;

        $body = $request->input('body');
        $payment_method = $body['payment_method'];
        $customer_phonenumber = $body['customer_phonenumber'];
        $cart = $body['cart'];
        $total = $body['total'];
        $insured = $body['insured'];

        $work_wallet_id = (Wallet::where('user_id', $business_id)->first())->wallet_id;

        try {
            $customer = Customer::where('phonenumber', $customer_phonenumber)->first();
            $customer_id = $customer->customer_id;
        }
        catch (\Throwable $e) {
            $content = 'This phonenumber is unregistered.';
            $status = 200;
            return response($content, $status);
            exit();
        }

        if ($payment_method == 'credit') {

            $customer_status = $customer->status;
            if ($customer_status == 'finlock') {
                $content = 'This user is not allowed to take more credit til they pay off old debt.';
                $status = 200;
                return response($content, $status);
                exit();
            }
            else {
                //pass
            }

            if ($insured == 'Y') {
                //check if business can afford this transaction
                $transaction_rate = (Rate::where('rate_name', 'abtrrate')->first())->percentage_rate;
                $insurance_rate = (Rate::where('rate_name', 'abinsrate')->first())->percentage_rate;
                $total_cost = $total + ($total*$transaction_rate/100 + $total*$insurance_rate/100);
                $rate_cost = $total*$transaction_rate/100 + $total*$insurance_rate/100;

                //fetch work wallet to check presence of funds
                $work_wallet_amount = (Wallet::where('user_id', $business_id)->first())->balance;
                if ($work_wallet_amount > $rate_cost) {
                    $wallet_balance = (Wallet::where('wallet_id', $work_wallet_id)->first())->balance;
                    $new_wallet_balance = $wallet_balance-$rate_cost;
                    $wallet = Wallet::where('wallet_id', $work_wallet_id)->first();
                    $wallet->balance = $new_wallet_balance;
                    $wallet->save();
                }
                else if ($rate_cost > $work_wallet_amount) {
                    $content = "Not enough work cash. You need atleast ".$rate_cost." Shs to complete this transacion.";
                    $status = 200;
                    return response($content, $status);
                    exit();
                }
            }

            else if ($insured == 'N' ) {
                //check if business can afford this transaction
                $transaction_rate = (Rate::where('rate_name', 'abtrrate')->first())->percentage_rate;
                $total_cost = $total + ($total*$transaction_rate/100);
                $rate_cost = $total*$transaction_rate/100;

                //fetch work wallet to check presence of funds
                $work_wallet_amount = (Wallet::where('user_id', $business_id)->first())->balance;
                if ($work_wallet_amount > $rate_cost) {

                    $wallet_balance = (Wallet::where('wallet_id', $work_wallet_id)->first())->balance;
                    $new_wallet_balance = $wallet_balance - $rate_cost;
                    $wallet = Wallet::where('wallet_id', $work_wallet_id)->first();
                    $wallet->balance = $new_wallet_balance;
                    $wallet->save();

                }
                else if ($rate_cost > $work_wallet_amount) {
                    $content = "Not enough work cash. You need atleast ".$rate_cost." Shs to complete this transacion.";
                    $status = 200;
                    return response($content, $status);
                    exit();
                }

            }

            $payback_date = $body['payback_date'];
            if($payback_date == '1w') {
                $payback_date = date('Y-m-d', strtotime("+1 week"));
            }
            else if($payback_date == '2w') {
                $payback_date = date('Y-m-d', strtotime("+2 week"));
            }
            else if($payback_date == '1m') {
                $payback_date = date('Y-m-d', strtotime("+4 week"));
            }
        }
        else {
            //check if business can afford this transaction
            $transaction_rate = (Rate::where('rate_name', 'abtrrate')->first())->percentage_rate;

            $total_cost = $total + ($total*$transaction_rate/100);
            $rate_cost = $total*$transaction_rate/100;

            //fetch work wallet to check presence of funds
            $work_wallet_amount = (Wallet::where('user_id', $business_id)->first())->balance;
            if ($work_wallet_amount > $rate_cost) {

                $wallet_balance = (Wallet::where('wallet_id', $work_wallet_id)->first())->balance;
                $new_wallet_balance = $wallet_balance - $rate_cost;
                $wallet = Wallet::where('wallet_id', $work_wallet_id)->first();
                $wallet->balance = $new_wallet_balance;
                $wallet->save();

            }
            else if ($rate_cost > $work_wallet_amount) {
                $content = "Not enough work cash. You need atleast ".$rate_cost." Shs to complete this transacion.";
                $status = 200;
                return response($content, $status);
                exit();
            }
            $payback_date = 'NA';
        }


        if ($cart == 'yes') {
            $cart_data = $body['cart_data'];

            $Sale = new Transaction;
            $Sale->transactions_id = uniqid();
            $Sale->type = $payment_method;
            $Sale->part_1 = $business_id;
            $Sale->part_2 = $customer_id;
            $Sale->state = 'pending';
            $Sale->due_by = $payback_date;
            $Sale->insured = $insured;

            $transactions_data_id = uniqid();
            $Sale->transactions_data_id = $transactions_data_id;

            $total_recount = 0;
            foreach ($cart_data as $data) {
                $transations_data = new TransactionsData;
                $transations_data->data_id = $transactions_data_id;
                $transations_data->item = $data['product_name'];
                $transations_data->qty = $data['quantity'];
                $transations_data->price = $data['price'];
                $transations_data->save();
                $total_recount += $data['price'];
            }

            $total = $total_recount;
            $Sale->total = $total;
            $Sale->save();

            $content = 'Your transaction has been inititated.';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else {
            $Sale = new Transaction;
            $Sale->transactions_id = uniqid();
            $Sale->type = $payment_method;
            $Sale->part_1 = $business_id;
            $Sale->part_2 = $customer_id;
            $Sale->state = 'pending';
            $Sale->due_by = $payback_date;
            $Sale->total = $total;
            $Sale->insured = $insured;
            $Sale->transactions_data_id = 'NA';
            $Sale->save();

            $content = 'Your transaction has been inititated';
            $status = 200;
            return response($content, $status);
            exit();
        }
    }

    public function getworkcash(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $business_cash_amount = (Wallet::where('user_id', $business_id)->first())->balance;

        return $business_cash_amount;

    }

    public function addworkcash(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $amount = $body['amount'];
        $mmpin = $body['pin'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $business_phonenumber = (Business::where('business_id', $business_id)->first())->phonenumber;
        $wallet_id = (Wallet::where('user_id', $business_id)->first())->wallet_id;

        //check phonenumber status
        if ($this->mmphonenumbercheck($business_phonenumber)) {//pass
        }
        else if (!$this->mmphonenumbercheck($business_phonenumber)) {
            $content = 'Unregistered Phonenumber';
            $status = 200;
            return response($content, $status);
            exit();
        }

        //check if number has amount
        if ($this->checkmmamount($business_phonenumber, $amount)) {//pass
        }
        else if (!$this->checkmmamount($business_phonenumber, $amount)) {
            $content = 'Insufficient Amount';
            $status = 200;
            return response($content, $status);
            exit();
        }
        
        //transfer amount
        $cashpacket = json_encode(array(
            "phonenumber" => $business_phonenumber,
            "amount" => $amount,
            "mmpin" => $mmpin,
            "wallet_id" => $wallet_id
        ));

        return $this->loadworkcash($cashpacket); 
    }

    public function getnotifs(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['recipient_id' => $business_id];
        $notification = Notification::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

        return $notification;

    }

    public function getrecords(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['part_1' => $business_id, 'state' => 'approved'];
        $transactions = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

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

    public function searchrecords(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $phonenumber = $body['phonenumber'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        try {
            $customer_id = (Customer::where('phonenumber', $phonenumber)->first())->customer_id;
        }
        catch (\Throwable $e) {
            $content = 'PhonenumberNotFound';
            $status = 200;
            return response($content, $status);
        }
        
        $search = ['part_1' => $business_id, 'part_2' => $customer_id, 'state' => 'approved'];
        
        $transactions = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

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

    public function paidoff(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $transaction_id = $body['transaction_id'];
        $pin_input = $body['pin'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $pin_hash = (Business::where('business_id', $business_id)->first())->pin;

        if(password_verify($pin_input, $pin_hash)){
            $search = ['part_1' => $business_id, 'type' => 'credit', 'transactions_id' => $transaction_id, 'state' => 'approved'];
            $tr = Transaction::where($search)->first();
            $tr->state = 'paidoff';
            $tr->save();

            $content = 'PaidOff';
            $status = 200;
            return response($content, $status);
        }
        else{
            $content = 'InvalidPin';
            $status = 200;
            return response($content, $status);
        }

    }

    public function getcreditors(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['part_1' => $business_id, 'type' => 'credit', 'state' => 'approved'];
        $creditors = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

        foreach ($creditors as $creditor) {
            $creditor_id = $creditor->part_2;
            $creditor_acc = Customer::where('customer_id', $creditor_id)->first();
            $creditor->fname = $creditor_acc->fname;
            $creditor->lname = $creditor_acc->lname;
            $creditor->phonenumber = $creditor_acc->phonenumber;
            $creditor->amount = $creditor->total;
            $creditor->date = date("F jS, Y", strtotime($creditor->due_by));
            $transaction_data_id = $creditor->transactions_data_id;
            $transactions_data = TransactionsData::where('data_id', $transaction_data_id)->get();
            $creditor->data = $transactions_data;

            $creditor->makeHidden('part_1');
            $creditor->makeHidden('part_2');
            $creditor->makeHidden('created_at');
            $creditor->makeHidden('type');
            $creditor->makeHidden('state');
            $creditor->makeHidden('due_by');
        }

        return $creditors;

    }

    public function searchcreditors(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $phonenumber = $body['phonenumber'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;

        try {
            $customer_id = (Customer::where('phonenumber', $phonenumber)->first())->customer_id;
        }
        catch (\Throwable $e) {
            $content = 'PhonenumberNotFound';
            $status = 200;
            return response($content, $status);
        }

        $search = ['part_1' => $business_id, 'part_2' => $customer_id, 'type' => 'credit', 'state' => 'approved'];
        $creditors = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

        foreach ($creditors as $creditor) {
            $creditor_id = $creditor->part_2;
            $creditor_acc = Customer::where('customer_id', $creditor_id)->first();
            $creditor->fname = $creditor_acc->fname;
            $creditor->lname = $creditor_acc->lname;
            $creditor->phonenumber = $creditor_acc->phonenumber;
            $creditor->amount = $creditor->total;
            $creditor->date = date("F jS, Y", strtotime($creditor->due_by));
            $transaction_data_id = $creditor->transactions_data_id;
            $transactions_data = TransactionsData::where('data_id', $transaction_data_id)->get();
            $creditor->data = $transactions_data;

            $creditor->makeHidden('part_1');
            $creditor->makeHidden('part_2');
            $creditor->makeHidden('created_at');
            $creditor->makeHidden('type');
            $creditor->makeHidden('state');
            $creditor->makeHidden('due_by');
        }

        return $creditors;

    }

    public function getbizinfo(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $business_id = (Session::where('session_id', $session_id)->first())->user_id;
        $business = Business::where('business_id', $business_id)->first();

        $response = [];
        $response_business_name = $business->name;
        $response_branch = $business->branch;
        $response_email = $business->email;
        $response_phonenumber = $business->phonenumber;
        $response_location = $business->location;

        $insurance_rate = (Rate::where('rate_name', 'abinsrate')->first())->percentage_rate;
        $refundable_rate =  100 - $insurance_rate;

        $total_credit_offered = 0;
        $total_credit_insured = 0;
        $total_insurance_refundable = $total_credit_insured*$refundable_rate/100;
        $total_mm_transacted = 0;
        $total_cash_transactions = 0;

        $find1 = ['part_1' => $business_id, 'type' => 'cash', 'state' => 'approved'];
        $business_transactions_1 = Transaction::where($find1)->get();
        foreach ($business_transactions_1 as $tr) {
            $total_cash_transactions += $tr->total;
        }
        $find2 = ['part_1' => $business_id, 'type' => 'mobile_money', 'state' => 'approved'];
        $business_transactions_2 = Transaction::where($find2)->get();
        foreach ($business_transactions_2 as $tr) {
            $total_mm_transacted += $tr->total;
        }
        $find3 = ['part_1' => $business_id, 'type' => 'credit', 'state' => 'approved', 'insured' => 'Y'];
        $business_transactions_3 = Transaction::where($find3)->get();
        foreach ($business_transactions_3 as $tr) {
            $total_credit_insured += $tr->total;
        }
        $find4 = ['part_1' => $business_id, 'type' => 'credit', 'state' => 'approved'];
        $business_transactions_4 = Transaction::where($find4)->get();
        foreach ($business_transactions_4 as $tr) {
            $total_credit_offered += $tr->total;
        }

        $response_total_credit_offered = $total_credit_offered;
        $response_total_credit_insured = $total_credit_insured;
        $response_total_insurance_refundable = $total_insurance_refundable;
        $response_total_mm_transacted = $total_mm_transacted;
        $response_total_cash_transactions = $total_cash_transactions;

        array_push($response, array(
            "name" => $response_business_name,
            "branch" => $response_branch,
            "email" => $response_email,
            "phonenumber" => $response_phonenumber,
            "location" => $response_location,
            "credit_offered" => $response_total_credit_offered,
            "insured_credit" => $response_total_credit_insured,
            "insurance_refundable" => $response_total_insurance_refundable,
            "mm_transacted" => $response_total_mm_transacted,
            "cash_transacted" => $response_total_cash_transactions
        ));

        return $response;

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
