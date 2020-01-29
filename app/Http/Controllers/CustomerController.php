<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Customer;
USe App\Business;
use App\Session;
use App\TestEmail;
Use App\TestSms;
Use App\Wallet;
Use App\Token;
use App\Notification;
USe App\SessionRecord;
Use App\Transaction;
Use App\TransactionsData;
Use App\Rate;
Use App\TestMM;

class CustomerController extends Controller
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
            return true;
        }
        catch (\Throwable $e) {
            return false;            
        }

    }

    public function checkmmamount($mm_number, $amount){

        //check if number has amount
        $mm_amount = (TestMM::where('phonenumber', $mm_number)->first())->amount;
        if ($mm_amount> $amount) {
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

    public function tokenverify(Request $request) {
        $req = $request->input('body');
        $input_token = $req['token'];

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];


        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $target_token = (Token::where('user_id', $customer_id)->first())->token_hash;

        if(password_verify($input_token, $target_token))
        {
            $verified_customer = Customer::where('customer_id', $customer_id)->first();
            $verified_customer->status = "verified";
            $verified_customer->save();


            $notificationpack = json_encode(array(
                'recipient_id' => $customer_id,
                'type' => 'Welcome',
                'title' => 'Welcome to the Akellobankernumber family',
                'message' => 'Welcome to the akellobanker creditfamily. We aim to provide reliable credit and support services for businesses and shoppers. Please read through our community guidelines and contact us through our support phonenumbers if you have any questions',
                'post_script' => 'Akellobanker Credit cloud is under Akellobanker. All rights reserved.'
            ));
            $this->sendnotification($notificationpack);

            $target_token_del = Token::where('user_id', $customer_id)->first();
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

    public function getappr(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;

        $search = ['part_2' => $customer_id, 'state' => 'pending'];
        $transactions = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

        foreach($transactions as $transaction){

            $transaction->transactions_id = $transaction->transactions_id;
            $transaction->type = $transaction->type;
            $transaction->business = (Business::where('business_id', $transaction->part_1)->first())->name;
            $transaction->state = $transaction->state;
            $transaction->due_by = $transaction->due_by;
            $transaction->total = $transaction->total;
            $transaction->insured = $transaction->insured;
            $transaction->transactions_data = TransactionsData::where('data_id', $transaction->transactions_data_id)->get();
            $transaction->date = date("F jS, Y", strtotime($transaction->created_at));

            $transaction->makeHidden('part_1');
            $transaction->makeHidden('part_2');
            $transaction->makeHidden('due_by');
            $transaction->makeHidden('created_at');
            $transaction->makeHidden('transactions_data_id');
        }

        return $transactions;
    }

    public function statusverify(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $status = (Customer::where('customer_id', $customer_id)->first())->status;
        return $status;

    }

    public function approve(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $transaction_id = $body['transaction_id'];
        $input_pin = $body['pin'];
        $pin2 = $body['pin2'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;

        $pin_hash = (Customer::where('customer_id', $customer_id)->first())->pin;

        if(password_verify($input_pin, $pin_hash)){
            $search = ['part_2' => $customer_id, 'transactions_id' => $transaction_id];
            $transaction = Transaction::where($search)->first();
            $business_id = $transaction->part_1;
            $amount = $transaction->total;
            $type = $transaction->type;
            $insured = $transaction->insured;
            $state = $transaction->state;

            if ($state == 'declined') {
                exit();
            }

            else if ($state == 'approved') {
                exit();
            }

            else if ($state == 'pending') {

                if ($type == 'credit') {
                    $target_wallet = ['user_id' => $customer_id, 'type' => 'credit'];
                    $wallet = Wallet::where($target_wallet)->first();
                    $current_amount = $wallet->balance;
                    $newamount = $current_amount - $amount;
                    $wallet->balance = $newamount;
                    $wallet->save();
                }
                else if ($type == 'cash') {
                    //
                }
                else if ($type == 'mobile_money') {

                    $customer_phonenumber = (Customer::where('customer_id', $customer_id)->first())->phonenumber;

                    $account = TestMM::where('phonenumber', $customer_phonenumber)->first();
                    $pin_hash = $account->pin;

                    if(password_verify($pin2, $pin_hash)){
                        $amount_present = $account->amount;
                        if ($amount_present < $amount) {
                            $content = 'InsufficientFunds';
                            $status = 200;
                            return response($content, $status);
                        }
                        else if ($amount_present > $amount) {
                            $newamount = $amount_present - $amount;
                            $account->amount = $newamount;
                            $account->save();
                        }
                    }
                    else
                    {
                        $content = 'InvalidPin2';
                        $status = 200;
                        return response($content, $status);
                    }
                }
            }

            $transaction->state = "approved";
            $transaction->save();

            $search = ['transactions_id' => $transaction_id];
            $target_transaction = Transaction::where($search)->first();

            $business_id = $target_transaction->part_1;
            $business = Business::where('business_id', $business_id)->first();
            $business_phonenumber = $business->phonenumber;
            $business_email = $business->email;

            $customer = Customer::where('customer_id', $customer_id)->first();
            $customer_fname = $customer->fname;
            $customer_lname = $customer->lname;
            $customer_email = $customer->email;
            $customer_phonenumber = $customer->phonenumber;

            $notificationpack = json_encode(array(
                'recipient_id' => $business_id,
                'type' => 'approval',
                'title' => 'Credit transaction approval',
                'message' => ''.$customer_fname.' '.$customer_lname.' '.$customer_phonenumber.' has approved a '.$type.' transaction of '.$amount.' Shs',
                'post_script' => 'Please contact us if you did not initiate this action.'
            ));
            $this->sendnotification($notificationpack);

            $emailpack = json_encode(array(
                'to_email' => $business_email,
                'title' => 'Credit transaction approval',
                'message' => ''.$customer_fname.' '.$customer_lname.' '.$customer_phonenumber.' has approved a '.$type.' transaction of '.$amount.' Shs'
            ));
            $this->sendemail($emailpack);

            $smspack = json_encode(array(
                'to_phonenumber' => $business_phonenumber,
                'title' => 'Credit transaction approval',
                'message' => ''.$customer_fname.' '.$customer_lname.' '.$customer_phonenumber.' has approved a '.$type.' transaction of '.$amount.' Shs'
            ));
            $this->sendsms($smspack);

            //send to customer
            $notificationpack = json_encode(array(
                'recipient_id' => $customer_id,
                'type' => 'approval',
                'title' => 'Credit transaction approval',
                'message' => 'You have approved a '.$type.' transaction of '.$amount.' Shs to '.$business_phonenumber.'',
                'post_script' => 'Please contact us if you did not initiate this action.'
            ));
            $this->sendnotification($notificationpack);

            $emailpack = json_encode(array(
                'to_email' => $customer_email,
                'title' => 'Credit transaction approval',
                'message' => 'You have approved a '.$type.' transaction of '.$amount.' Shs to '.$business_phonenumber.''
            ));
            $this->sendemail($emailpack);

            $smspack = json_encode(array(
                'to_phonenumber' => $customer_phonenumber,
                'title' => 'Credit transaction approval',
                'message' => 'You have approved a '.$type.' transaction of '.$amount.' Shs to '.$business_phonenumber.''
            ));
            $this->sendsms($smspack);

            $content = 'TransactionApproved';
            $status = 200;
            return response($content, $status);
        }
        else {
            $content = 'InvalidPin';
            $status = 200;
            return response($content, $status);
        }

    }
    public function decline(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $transaction_id = $body['transaction_id'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['part_2' => $customer_id, 'transactions_id' => $transaction_id];
        $target_transaction = Transaction::where($search)->first(); 
        $target_transaction->state = "declined";
        $target_transaction->save();

        //get business
        $transaction = Transaction::where('part_2', $customer_id)->first();
        $business_id = $transaction->part_1;
        $amount = $transaction->total;
        $insured = $transaction->insured;

        $state = $transaction->state;
        if ($state == 'declined') {
            $content = 'TransactionDeclined';
            $status = 200;
            return response($content, $status);
            exit();
        }

        $transaction_rate = (Rate::where('rate_name', 'abtrrate')->first())->percentage_rate;
        $insurance_rate = (Rate::where('rate_name', 'abinsrate')->first())->percentage_rate;

        if ($insured == "Y") {
            $refund = $amount*$transaction_rate/100 + $amount*$insurance_rate/100;
            //return money
            $business_balance = (Wallet::where('user_id', $business_id)->first())->balance;
            $business_wallet = Wallet::where('user_id', $business_id)->first();
            $business_wallet->balance = $business_balance + $refund;
            $business_wallet->save();
        }

        else if ($insured == "N") {
            $refund = $amount*$transaction_rate/100;
            //return money
            $business_balance = (Wallet::where('user_id', $business_id)->first())->balance;
            $business_wallet = Wallet::where('user_id', $business_id)->first();
            $business_wallet->balance = $business_balance + $refund;
            $business_wallet->save();
        }

        $content = 'TransactionDeclined';
        $status = 200;
        return response($content, $status);

    }

    public function getnotifs(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $customer_id = (Session::where('session_id', $session_id)->orderBy('created_at', 'DESC')->first())->user_id;
        $search = ['recipient_id' => $customer_id];
        $notification = Notification::where($search)->orderBy('created_at', 'DESC')->paginate(15);

        return $notification;
    }

    public function getwallets(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $wallets = Wallet::where('user_id', $customer_id)->get();

        return $wallets;
    }

    public function getrecords(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['part_2' => $customer_id, 'state' => 'approved'];
        $transactions = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

        foreach($transactions as $transaction){

            $transaction->state = $transaction->state;
            $transaction->due_by = date("F jS, Y", strtotime($transaction->due_by));
            $transaction->business = (Business::where('business_id', $transaction->part_1)->first())->name;
            $transaction->phonenumber = (Business::where('business_id', $transaction->part_1)->first())->phonenumber;
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

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        try {
            $business_id = (Business::where('phonenumber', $phonenumber)->first())->business_id;
        }
        catch (\Throwable $e) {
            $content = 'PhonenumberNotFound';
            $status = 200;
            return response($content, $status);
        }

        $search = ['part_1' => $business_id, 'part_2' => $customer_id, 'state' => 'approved'];
        $transactions = Transaction::where($search)->orderBy('created_at', 'DESC')->paginate(1000);

        foreach($transactions as $transaction){

            $transaction->state = $transaction->state;
            $transaction->due_by = date("F jS, Y", strtotime($transaction->due_by));
            $transaction->business = (Business::where('business_id', $transaction->part_1)->first())->name;
            $transaction->phonenumber = (Business::where('business_id', $transaction->part_1)->first())->phonenumber;
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

    public function getcredit(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $search = ['part_2' => $customer_id, 'type' => 'credit', 'state' => 'approved'];
        $credit = Transaction::where($search)->orderBy('created_at', 'DESC')->get();
        foreach ($credit as $cr) {
            $cr->businessname = (Business::where('business_id', $cr->part_1)->first())->name;
            $cr->due_by_date = date("F jS, Y", strtotime($cr->due_by));

            $cr->makeHidden('created_at');
            $cr->makeHidden('due_by');
            $cr->makeHidden('insured');
            $cr->makeHidden('part_1');
            $cr->makeHidden('part_2');
            $cr->makeHidden('state');
            $cr->makeHidden('transactions_data_id');
            $cr->makeHidden('type');
        }

        return $credit;
    }

    public function mm2wallet(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');

        $amount = $body['amount'];
        $pin = $body['pin'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $phonenumber = (Customer::where('customer_id', $customer_id)->first())->phonenumber;

        $account = TestMM::where('phonenumber', $phonenumber)->first();
        $account_amount = $account->amount;

        if ($account_amount > $amount){}
        else if ($amount > $account_amount) {
            $content = 'Insufficient Funds On Mobile Money';
            $status = 200;
            return response($content, $status);
            exit();
        }

        $pin_hash = $account->pin;

        if(password_verify($pin, $pin_hash)){
            $newamount = $account_amount - $amount;
            $account->amount = $newamount;
            $account->save();

            $business_account = TestMM::where('phonenumber', '0781077344')->first();
            $business_account_amount = $business_account->amount;
            $new_business_account_amount = $business_account_amount + $amount;
            $business_account->amount = $new_business_account_amount;
            $business_account->save();

            $search = ['user_id' => $customer_id, 'type' => 'recipient'];
            $wallet = Wallet::where($search)->first();
            $wallet_amount = $wallet->balance;
            $new_wallet_amount = $wallet_amount + $amount;
            $wallet->balance = $new_wallet_amount;
            $wallet->save();

            $customer = Customer::where('customer_id', $customer_id)->first();
            $customer_email = $customer->email;
            $customer_phonenumber = $customer->phonenumber;

            //send to customer
            $notificationpack = json_encode(array(
                'recipient_id' => $customer_id,
                'type' => 'deposit',
                'title' => 'Credit Deposit',
                'message' => 'You have deposited '.$amount.' Shs to your wallet.',
                'post_script' => 'Please contact us if you did not initiate this action.'
            ));
            $this->sendnotification($notificationpack);

            $emailpack = json_encode(array(
                'to_email' => $customer_email,
                'title' => 'Credit Deposit',
                'message' => 'You have deposited '.$amount.' Shs to your wallet.'
            ));
            $this->sendemail($emailpack);

            $smspack = json_encode(array(
                'to_phonenumber' => $customer_phonenumber,
                'title' => 'Credit Deposit',
                'message' => 'You have deposited '.$amount.' Shs to your wallet.'
            ));
            $this->sendsms($smspack);

            $content = 'Cash Added To Wallet';
            $status = 200;
            return response($content, $status);
            exit();

        }
        else{
            $content = 'Invalid Mobile Money Pin';
            $status = 200;
            return response($content, $status);
            exit();
        }
    }

    public function getnamesbynumber(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $body = $request->input('body');
        $phonenumber = $body['phonenumber'];

        $user_account = Customer::where('phonenumber', $phonenumber)->first();
        try {
            $names = ''.$user_account->fname.' '.$user_account->lname.'';
        }
        catch (\Throwable $e) {
            $names = 'This phonenumber is not registered here.';
        }

        return $names;

    }

    public function wallet2wallet(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $from_phonenumber = (Customer::where('customer_id', $customer_id)->first())->phonenumber;
        $from_pin = (Customer::where('customer_id', $customer_id)->first())->pin;

        $body = $request->input('body');

        $phonenumber = $body['phonenumber'];
        $amount = $body['amount'];
        $pin = $body['pin'];

        $from_user_id = (Customer::where('phonenumber', $from_phonenumber)->first())->customer_id;
        $params = ['user_id' => $from_user_id, 'type' => 'recipient'];
        $from_wallet = Wallet::where($params)->first();
        $from_wallet_amount = $from_wallet->balance;

        $target_user_id = (Customer::where('phonenumber', $phonenumber)->first())->customer_id;
        $params = ['user_id' => $target_user_id, 'type' => 'recipient'];
        $target_wallet = Wallet::where($params)->first();
        $target_wallet_amount = $target_wallet->balance;

        if ($amount >= $from_wallet_amount) {
            $content = 'Insufficient Funds In Wallet';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else if ($from_wallet_amount >= $amount) {
            //verfiy user
            if(password_verify($pin, $from_pin)){
                $new_from_wallet_amount = $from_wallet_amount - $amount;
                $from_wallet->balance = $new_from_wallet_amount;
                $from_wallet->save();

                $new_target_wallet_amount = $target_wallet_amount + $amount;
                $target_wallet->balance = $new_target_wallet_amount;
                $target_wallet->save();

                $customer = Customer::where('customer_id', $customer_id)->first();
                $customer_fname = $customer->fname;
                $customer_lname = $customer->lname;
                $customer_email = $customer->email;
                $customer_phonenumber = $customer->phonenumber;

                //target user names
                $target = Customer::where('customer_id', $target_user_id)->first();
                $target_fname = $target->fname;
                $target_lname = $target->lname;
                $target_email = $target->email;
                $target_phonenumber = $target->phonenumber;

                //send to customer
                $notificationpack = json_encode(array(
                    'recipient_id' => $customer_id,
                    'type' => 'reciept',
                    'title' => 'Sent Credit',
                    'message' => 'You have sent '.$amount.' Shs to '.$target_fname.' '.$target_lname.'.',
                    'post_script' => 'Please contact us if you did not initiate this action.'
                ));
                $this->sendnotification($notificationpack);

                $emailpack = json_encode(array(
                    'to_email' => $customer_email,
                    'title' => 'Sent Credit',
                    'message' => 'You have sent '.$amount.' Shs to '.$target_fname.' '.$target_lname.'.'
                ));
                $this->sendemail($emailpack);

                $smspack = json_encode(array(
                    'to_phonenumber' => $customer_phonenumber,
                    'title' => 'Sent Credit',
                    'message' => 'You have sent '.$amount.' Shs to '.$target_fname.' '.$target_lname.'.'
                ));
                $this->sendsms($smspack);

                //send to other customer
                $notificationpack = json_encode(array(
                    'recipient_id' => $target_user_id,
                    'type' => 'reciept',
                    'title' => 'You have recieved credit',
                    'message' => 'You have recieved '.$amount.' Shs from '.$customer_fname.' '.$customer_lname.'.',
                    'post_script' => 'Please contact us if you did not initiate this action.'
                ));
                $this->sendnotification($notificationpack);

                $emailpack = json_encode(array(
                    'to_email' => $target_email,
                    'title' => 'You have recieved credit',
                    'message' => 'You have recieved '.$amount.' Shs from '.$customer_fname.' '.$customer_lname.'.'
                ));
                $this->sendemail($emailpack);

                $smspack = json_encode(array(
                    'to_phonenumber' => $target_phonenumber,
                    'title' => 'You have recieved credit',
                    'message' => 'You have recieved '.$amount.' Shs from '.$customer_fname.' '.$customer_lname.'.'
                ));
                $this->sendsms($smspack);

                $content = 'Transafered '.$amount.' to '.$target_fname.' '.$target_lname.'.';
                $status = 200;
                return response($content, $status);
                exit();
            }
            else{
                $content = 'Invalid Akellobanker Pin';
                $status = 200;
                return response($content, $status);
                exit();
            }
        }

    }

    public function creditPwallet(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;

        $body = $request->input('body');
        $transaction_id = $body['transaction_id'];
        $input_pin = $body['pin'];

        $pin_hash = (Customer::where('customer_id', $customer_id)->first())->pin;

        if (password_verify($input_pin, $pin_hash)){
            $params = ['transactions_id' => $transaction_id, 'part_2' => $customer_id, 'type' => 'credit', 'state' => 'approved'];
            $transaction = Transaction::where($params)->first();

            try {
                $amount = $transaction->total;
            }
            catch (\Throwable $e) {
                $content = 'Invalid Credit Transaction';
                $status = 200;
                return response($content, $status);
                exit();
            }
        
            $business_phonenumber = (Business::where('business_id', $transaction->part_1)->first())->phonenumber;

            $search = ['user_id' => $customer_id, 'type' => 'recipient'];
            $wallet = Wallet::where($search)->first();
            $wallet_amount = $wallet->balance;

            if ($wallet_amount > $amount) {
                //reflect change in credit wallet amount
                $new_wallet_amount = $wallet_amount - $amount;
                $wallet->balance = $new_wallet_amount;

                if ($wallet->balance < 0) {
                    //pass
                }
                else{
                    //update status if has been blocked
                    $customer = Customer::where('customer_id', $customer_id)->first();
                    $customer->status = 'verified';
                    $customer->save();
                }

                $wallet->save();

                //take from ab account
                $ab_account = TestMM::where('phonenumber', '0781077344')->first();
                $ab_account_amount = $ab_account->amount;
                $new_ab_account_amount = $ab_account_amount - $amount;
                $ab_account->amount = $new_ab_account_amount;
                $ab_account->save();

                //add to business mm
                $business_account = TestMM::where('phonenumber', $business_phonenumber)->first();
                $business_account_amount = $business_account->amount;
                $new_business_account_amount = $business_account_amount + $amount;
                $business_account->amount = $new_business_account_amount;
                $business_account->save();

                //update transaction
                $transaction->state = 'paidoff';
                $transaction->save();

                $content = 'Credit Paid Off';
                $status = 200;
                return response($content, $status);
                exit();
            }
            else if ($amount > $wallet_amount) {
                $content = 'Insufficient Funds In Wallet';
                $status = 200;
                return response($content, $status);
            }
        }
        else{
            $content = 'Invalid Akellobanker Pin';
            $status = 200;
            return response($content, $status);
        }
    }

    public function creditPMM(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;

        $body = $request->input('body');
        $transaction_id = $body['transaction_id'];
        $input_pin = $body['pin'];

        $customer_account = Customer::where('customer_id', $customer_id)->first();
        $customer_mm_number = $customer_account->phonenumber;
        $customer_mm = TestMM::where('phonenumber', $customer_mm_number)->first();

        $pin_hash = $customer_mm->pin;

        if (password_verify($input_pin, $pin_hash)){

            $params = ['transactions_id' => $transaction_id, 'part_2' => $customer_id, 'type' => 'credit', 'state' => 'approved'];

            $transaction = Transaction::where($params)->first();

            try {
                $amount = $transaction->total;
            }
            catch (\Throwable $e) {
                $content = 'Invalid Transaction';
                $status = 200;
                return response($content, $status);
            }

            $business_phonenumber = (Business::where('business_id', $transaction->part_1)->first())->phonenumber;

            $phonenumber = (Customer::where('customer_id', $customer_id)->first())->phonenumber;
            $account = TestMM::where('phonenumber', $phonenumber)->first();
            $account_amount = $account->amount;


            if ($account_amount > $amount) {
                $newamount = $account_amount - $amount;
                $account->amount = $newamount;
                $account->save();

                $business_account = TestMM::where('phonenumber', $business_phonenumber)->first();
                $business_account_amount = $business_account->amount;
                $new_business_account_amount = $business_account_amount + $amount;
                $business_account->amount = $new_business_account_amount;
                $business_account->save();

                $search = ['user_id' => $customer_id, 'type' => 'credit'];
                $wallet = Wallet::where($search)->first();
                $wallet_amount = $wallet->balance;
                $new_wallet_amount = $wallet_amount + $amount;
                $wallet->balance = $new_wallet_amount;

                if ($wallet->balance < 0) {
                    //pass
                }
                else{
                    //update status if has been blocked
                    $customer = Customer::where('customer_id', $customer_id)->first();
                    $customer->status = 'verified';
                    $customer->save();
                }

                $wallet->save();

                //update transaction
                $transaction->state = 'paidoff';
                $transaction->save();

                $content = 'Credit Paid Off';
                $status = 200;
                return response($content, $status);
                exit();
            }
            else if ($amount > $account_amount) {
                $content = 'Insufficient Funds In Mobile Money Account';
                $status = 200;
                return response($content, $status);
            }
        }
        else{
            $content = 'Invalid Mobile Money Pin';
            $status = 200;
            return response($content, $status);
        }
    }

    public function credit2MM(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];
        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $body = $request->input('body');
        $amount = $body['amount'];
        $pin = $body['pin'];

        //get wallet amount and compare
        $wallet = Wallet::where('user_id',  $customer_id)->first();
        $curr_wallet_amount = $wallet->balance;

        if ($amount > $curr_wallet_amount) {
            $content = 'Insufficient Funds In Wallet';
            $status = 200;
            return response($content, $status);
        }
        else if ($curr_wallet_amount > $amount) {
            //get test mm of user
            $phonenumber = (Customer::where('customer_id', $customer_id)->first())->phonenumber;
            $pin_hash = (Customer::where('customer_id', $customer_id)->first())->pin;

            if (password_verify($pin, $pin_hash)) {

                $mm = TestMM::where('phonenumber', $phonenumber)->first();
                $mm_amt = $mm->amount;
                $new_mm_amt = $mm_amt + $amount;
                $mm->amount = $new_mm_amt;
                $mm->save();

                //reduce wallet
                $new_wallet_amount = $curr_wallet_amount - $amount;
                $wallet->balance = $new_wallet_amount;
                $wallet->save();

                $customer = Customer::where('customer_id', $customer_id)->first();
                $customer_email = $customer->email;
                $customer_phonenumber = $customer->phonenumber;

                //send to customer
                $notificationpack = json_encode(array(
                    'recipient_id' => $customer_id,
                    'type' => 'withdraw',
                    'title' => 'Withdrawn Credit',
                    'message' => 'You have withdrawn '.$amount.' Shs from wallet.',
                    'post_script' => 'Please contact us if you did not initiate this action.'
                ));
                $this->sendnotification($notificationpack);

                $emailpack = json_encode(array(
                    'to_email' => $customer_email,
                    'title' => 'Withdrawn Credit',
                    'message' => 'You have withdrawn '.$amount.' Shs from wallet.'
                ));
                $this->sendemail($emailpack);

                $smspack = json_encode(array(
                    'to_phonenumber' => $customer_phonenumber,
                    'title' => 'Withdrawn Credit',
                    'message' => 'You have withdrawn '.$amount.' Shs from wallet.'
                ));
                $this->sendsms($smspack);

                //respond
                $content = 'Credit Withdrawn to '.$customer_phonenumber.'.';
                $status = 200;
                return response($content, $status);

            }

            else {
                $content = 'Invalid Akellobanker Pin';
                $status = 200;
                return response($content, $status);

            }
        }

    }
    public function getaccinfo(Request $request) {

        $auth = $request->input('auth');
        $session_id = $auth['session_id'];

        $customer_id = (Session::where('session_id', $session_id)->first())->user_id;
        $customer = Customer::where('customer_id', $customer_id)->first();

        return $customer;
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
