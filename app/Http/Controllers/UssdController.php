<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Customer;
use App\Business;
Use App\Wallet;
Use App\Token;
Use App\Rate;
Use App\TestMM;
use App\TestEmail;
Use App\TestSms;
use App\Notification;
Use App\Transaction;
Use App\TransactionsData;

class UssdController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
    **/
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
            $content = 'Work Cash Loaded';
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

    public function receiver(Request $request){
        $id = $request->input('id');
        //the users phonenumber
        $msisdn = $id['msisdn'];
        $user_type = $id['user_type'];
        if ($user_type == "shopper") { $db = 'Customer'; }
        else if ($user_type == "merchant") { $db = 'Business'; }
        //check user types
        if($user_type == "shopper" || $user_type == "merchant" ) {
            //pass
        }
        else {
            $content = "InvalidUserType";
            $status = 200;
            return response($content, $status);
            exit();
        }
        //fetch user_id from using phonenumber and email
        try {
            $model = '\App\\'.$db;
            $user = $model::where('phonenumber', $msisdn)->first();
        }
        catch (\Throwable $e) {
            $content = $e;
            $status = 200;
            return response($content, $status);
            exit();
        }

        //request type
        $req = $request->input('request');
        $reqbody = $request->input('body');

        //read request type and initiated transaction
        return $this->$req($reqbody, $user);
    }

    public function account_information_shopper($data, $user) {
        return $user;
    }

    public function withdraw_credit($data, $user) {
        $customer_id = $user['customer_id'];
        $amount = $data['amount'];
        $pin = $data['akellobanker_pin'];

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

    public function pay_off_with_mm($data, $user) {

        $customer_id = $user['customer_id'];
        $transaction_id = $data['transaction_id'];
        $input_pin = $data['mobile_money_pin'];

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
    
    public function pay_off_with_wallet($data, $user) {
        $customer_id = $user['customer_id'];
        $transaction_id = $data['transaction_id'];
        $input_pin = $data['akellobanker_pin'];

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

    public function withdraw_from_wallet($data, $user) {
        $customer_id = $user['customer_id'];
        $amount = $data['amount'];
        $pin = $data['Akellobanker_Pin'];

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

    public function deposit_to_wallet($data, $user) {

        $amount = $data['amount'];
        $pin = $data['mobile_money_pin'];
        $customer_id = $user['customer_id'];

        $phonenumber = (Customer::where('customer_id', $customer_id)->first())->phonenumber;

        $account = TestMM::where('phonenumber', $phonenumber)->first();
        $account_amount = $account->amount;

        if ($account_amount > $amount){}
        else if ($amount > $account_amount) {
            $content = 'Insufficient Funds On Mobile Money Account';
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

    public function wallet_info($data, $user) {
        $customer_id = $user['customer_id'];
        $wallets = Wallet::where('user_id', $customer_id)->get();

        $wallets->makeHidden('user_id');

        return $wallets;
    }

    public function my_approvals($data, $user) {
        $customer_id = $user['customer_id'];

        $search = ['part_2' => $customer_id, 'state' => 'pending'];
        $transactions = Transaction::where($search)->get();

        foreach($transactions as $transaction){

            $transaction->transactions_id = $transaction->transactions_id;
            $transaction->type = $transaction->type;
            $transaction->business = (Business::where('business_id', $transaction->part_1)->first())->name;
            $transaction->state = $transaction->state;
            $transaction->due_by = $transaction->due_by;
            $transaction->total = $transaction->total;
            $transaction->insured = $transaction->insured;
            $transaction->date = date("F jS, Y", strtotime($transaction->created_at));

            $transaction->makeHidden('transactions_data');
            $transaction->makeHidden('part_1');
            $transaction->makeHidden('part_2');
            $transaction->makeHidden('due_by');
            $transaction->makeHidden('created_at');
            $transaction->makeHidden('transactions_data_id');
        }

        return $transactions;
    }

    public function approve_approval($data, $user) {
        $transaction_id = $data['transaction_id'];
        $input_pin = $data['akellobanker_pin'];
        $pin2 = $data['mobile_money_pin'];

        $customer_id = $user['customer_id'];
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
                $content = 'Transaction Already Approved';
                $status = 200;
                return response($content, $status);
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
                            $content = 'Insufficient Funds In Mobile Money Account';
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
                        $content = 'Invalid Mobile Money Pin';
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
                'message' => ''.$customer_fname.''.$customer_lname.' '.$customer_phonenumber.' has approved a '.$type.' transaction of '.$amount.' Shs',
                'post_script' => 'Please contact us if you did not initiate this action.'
            ));
            $this->sendnotification($notificationpack);

            $emailpack = json_encode(array(
                'to_email' => $business_email,
                'title' => 'Credit transaction approval',
                'message' => ''.$customer_fname.''.$customer_lname.' '.$customer_phonenumber.' has approved a '.$type.' transaction of '.$amount.' Shs'
            ));
            $this->sendemail($emailpack);

            $smspack = json_encode(array(
                'to_phonenumber' => $business_phonenumber,
                'title' => 'Credit transaction approval',
                'message' => ''.$customer_fname.''.$customer_lname.' '.$customer_phonenumber.' has approved a '.$type.' transaction of '.$amount.' Shs'
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

            $content = 'Transaction Approved';
            $status = 200;
            return response($content, $status);
        }
        else {
            $content = 'Invalid Akellobanker Pin';
            $status = 200;
            return response($content, $status);
        }
    }

    public function decline_approval($data, $user) {
        $transaction_id = $data['transaction_id'];
        $customer_id = $user['customer_id'];

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

    public function account_information_merchant($data, $user) {
        return $user;
    }

    public function work_cash_balance($data, $user) {
        $business_id = $user['business_id'];
        $business_cash_amount = (Wallet::where('user_id', $business_id)->first())->balance;
        return $business_cash_amount;
    }

    public function add_work_cash($data, $user) {
        $business_id = $user['business_id'];
        $amount = $data['amount'];
        $mmpin = $data['mobile_money_pin'];

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
            $content = 'Insufficient Amount In Mobile Money Account';
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

    public function sell_cash($data, $user) {
        $business_id = $user['business_id'];
        $customer_phonenumber = $data['customer_phonenumber'];
        $total = $data['total'];

        $work_wallet_id = (Wallet::where('user_id', $business_id)->first())->wallet_id;

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

        $Sale = new Transaction;
        $Sale->transactions_id = uniqid();
        $Sale->type = 'cash';
        $Sale->part_1 = $business_id;
        $Sale->part_2 = $customer_id;
        $Sale->state = 'pending';
        $Sale->due_by = 'NA';
        $Sale->total = $total;
        $Sale->insured = 'NA';
        $Sale->transactions_data_id = 'NA';
        $Sale->save();

        $content = 'Your transaction has been inititated';
        $status = 200;
        return response($content, $status);
        exit();
    }

    public function sell_credit($data, $user) {
        $business_id = $user['business_id'];
        $customer_phonenumber = $data['customer_phonenumber'];
        $total = $data['total'];
        $insured = $data['insured'];

        $work_wallet_id = (Wallet::where('user_id', $business_id)->first())->wallet_id;
        
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
        $payback_date = $data['payback_date'];
        if($payback_date == '1w') {
            $payback_date = date('Y-m-d', strtotime("+1 week"));
        }
        else if($payback_date == '2w') {
            $payback_date = date('Y-m-d', strtotime("+2 week"));
        }
        else if($payback_date == '1m') {
            $payback_date = date('Y-m-d', strtotime("+4 week"));
        }
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
        
        $Sale = new Transaction;
        $Sale->transactions_id = uniqid();
        $Sale->type = 'credit';
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

    public function sell_mobile_money($data, $user) {
        $business_id = $user['business_id'];
        $customer_phonenumber = $data['customer_phonenumber'];
        $total = $data['total'];

        $work_wallet_id = (Wallet::where('user_id', $business_id)->first())->wallet_id;

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

        $Sale = new Transaction;
        $Sale->transactions_id = uniqid();
        $Sale->type = 'mobile_money';
        $Sale->part_1 = $business_id;
        $Sale->part_2 = $customer_id;
        $Sale->state = 'pending';
        $Sale->due_by = 'NA';
        $Sale->total = $total;
        $Sale->insured = 'NA';
        $Sale->transactions_data_id = 'NA';
        $Sale->save();

        $content = 'Your transaction has been inititated';
        $status = 200;
        return response($content, $status);
        exit();
    }
}