<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Admin;
use App\Customer;
use App\Business;
use App\Session;
use App\TestEmail;
Use App\TestSms;
Use App\Wallet;
Use App\Token;
Use App\Rate;
Use App\TestMM;
Use App\CredentialReset;

class OpenController extends Controller
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

    public function TestEmails(Request $request) {
        $req = $request->input('body');
        $email_input = $req['email'];

        $data = TestEmail::where('to_email', $email_input)->orderBy('created_at', 'desc')->get();

        return $data;
    }

    public function TestSms(Request $request) {
        $req = $request->input('body');
        $phonenumber_input = $req['phonenumber'];

        $data = TestSms::where('to_number', $phonenumber_input)->orderBy('created_at', 'desc')->get();

        return $data;
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

    public function generateemptywallets($user_id){

        $wallet_types = ['recipient', 'credit'];
        foreach ($wallet_types as $wallet_type) {
            $wallet = new Wallet;
            $wallet_id = uniqid();
            $wallet->wallet_id = $wallet_id;
            $wallet->type = $wallet_type;
            $wallet->user_id = $user_id;
            $wallet->balance = 0;
            $wallet->save();
        }
    }

    public function generateworkwallet($user_id){

        $wallet_types = ['working'];
        foreach ($wallet_types as $wallet_type) {
            $wallet = new Wallet;
            $wallet_id = uniqid();
            $wallet->wallet_id = $wallet_id;
            $wallet->type = $wallet_type;
            $wallet->user_id = $user_id;
            $wallet->balance = 0;
            $wallet->save();
        }
    }

    public function newsession($session_pack){

        $session_data = json_decode($session_pack);
        $newsession = new Session;
        $session_id = uniqid();
        $newsession->session_id = $session_id;
        $newsession->user_id = $session_data->user_id;
        $token = bin2hex(random_bytes(16));
        $newsession->token = password_hash($token, PASSWORD_BCRYPT);
        $newsession->client= $session_data->user_id;
        $newsession->save();
        $response = json_encode(array(
            'token' => $token,
            'session_id' => $session_id
        ));
        return $response;
    }


    public function CredentialsReset(Request $request) {
        $req = $request->input('body');
        $email_input = $req['email'];
        $phonenumber_input = $req['phonenumber'];
        $user_type = $req['user_type'];
        $credential_type = $req['credential_type'];
        $new_passcode = $req['new_passcode'];

        if ($user_type == "admin") { $db = 'Admin'; $user_id_nom = 'admin_id'; }
        else if ($user_type == "shopper") { $db = 'Customer'; $user_id_nom = 'customer_id'; }
        else if ($user_type == "merchant") { $db = 'Business'; $user_id_nom = 'business_id'; }

        //fetch user_id from using phonenumber and email
        try {
            $searches = ['email' => $email_input, 'phonenumber' => $phonenumber_input];
            $model = '\App\\'.$db;
            $user_id = ($model::where($searches)->first())->$user_id_nom;
        }
        catch (\Throwable $e) {
            $content = 'EmailPhonenumberMatchNotFound';
            $status = 200;
            return response($content, $status);
            exit();
        }

        //check whether verification has been passed and it is reset time, also check if it has already been reset
        try {
            $searches = ['user_id' => $user_id, 'user_type' => $user_type, 'credential_type' => $credential_type];
            $reset_record = CredentialReset::where($searches)->orderBy('created_at', 'desc')->first();
        }
        catch (\Throwable $e) {
            $content = 'ResetRecordNotFound';
            $status = 200;
            return response($content, $status);
            exit();
        }
        if($reset_record->verified_state == 'unverified'){
            $content = 'Unverified';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else if($reset_record->verified_state == 'completed'){
            $content = 'ResetAlreadyComplete';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else if($reset_record->verified_state == 'verified'){
            try {
                $searches = ['email' => $email_input, 'phonenumber' => $phonenumber_input];
                $model = '\App\\'.$db;
                $user_account = $model::where($searches)->first();
                $user_account->$credential_type = password_hash($new_passcode, PASSWORD_BCRYPT);
                $user_account->save();
            }
            catch (\Throwable $e) {
                $content = $e;
                $status = 200;
                return response($content, $status);
                exit();
            }
            try {
                $searches = ['user_id' => $user_id, 'user_type' => $user_type, 'credential_type' => $credential_type];
                $reset_record = CredentialReset::where($searches)->first();
                $reset_record->verified_state = 'completed';
                $reset_record->save();
            }
            catch (\Throwable $e) {
                $content = 'ResetRecordNotUpdated';
                $status = 200;
                return response($content, $status);
                exit();
            }
            $content = 'PasswordChanged';
            $status = 200;
            return response($content, $status);
            exit();

        }
    }

    public function UserVerification(Request $request) {
        $req = $request->input('body');
        $email_input = $req['email'];
        $phonenumber_input = $req['phonenumber'];
        $user_type = $req['user_type'];
        $credential_type = $req['credential_type'];
        $email_key = $req['email_key'];
        $sms_key = $req['sms_key'];

        if ($user_type == "admin") { $db = 'Admin'; $user_id_nom = 'admin_id'; }
        else if ($user_type == "shopper") { $db = 'Customer'; $user_id_nom = 'customer_id'; }
        else if ($user_type == "merchant") { $db = 'Business'; $user_id_nom = 'business_id'; }

        //fetch user_id from using phonenumber and email
        try {
            $searches = ['email' => $email_input, 'phonenumber' => $phonenumber_input];
            $model = '\App\\'.$db;
            $user_id = ($model::where($searches)->first())->$user_id_nom;
        }
        catch (\Throwable $e) {
            $content = 'EmailPhonenumberMatchNotFound';
            $status = 200;
            return response($content, $status);
            exit();
        }

        //match tokens
        try {
            $searches2 = ['user_id' => $user_id, 'user_type' => $user_type, 'credential_type' => $credential_type];
            $reset_record = CredentialReset::where($searches2)->orderBy('created_at', 'desc')->first();
        }
        catch (\Throwable $e) {
            $content = 'ResetRecordNotFound';
            $status = 200;
            return response($content, $status);
            exit();
        }

        if(password_verify($sms_key, $reset_record->sms_key_hash) && password_verify($email_key, $reset_record->email_key_hash)){
            try {
                $reset_record->verified_state = 'verified';
                $reset_record->save();
            }
            catch (\Throwable $e) {
                $content = 'SaveError';
                $status = 200;
                return response($content, $status);
                exit();
            }

            $content = 'CodesVerified';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else{
            $content = 'ResetRecordNotFound';
            $status = 200;
            return response($content, $status);
            exit();
        }

    }

    public function UserIdentification(Request $request) {
        $req = $request->input('body');
        $user_type = $req['user_type'];
        $credential_type = $req['credential_type'];
        $email_input = $req['email'];
        $phonenumber_input = $req['phonenumber'];

        //set db target based on user_type
        if ($user_type == "admin") { $db = 'Admin'; $user_id_nom = 'admin_id'; }
        else if ($user_type == "shopper") { $db = 'Customer'; $user_id_nom = 'customer_id'; }
        else if ($user_type == "merchant") { $db = 'Business'; $user_id_nom = 'business_id'; }

        //test existence of email
        try {
            $model = '\App\\'.$db;
            $email_exist = ($model::where('email', $email_input)->first())->email;
        }
        catch (\Throwable $e) {
            $content = 'NoEmailFound';
            $status = 200;
            return response($content, $status);
            exit();
        }
        //test existence of phonenumber
        try {
            $model = '\App\\'.$db;
            $phonenumber_exist = ($model::where('phonenumber', $phonenumber_input)->first())->phonenumber;
        }
        catch (\Throwable $e) {
            $content = 'NoPhonenumberFound';
            $status = 200;
            return response($content, $status);
            exit();
        }
        //test existence of phonenumber and email together
        try {
            $searches = ['email' => $email_input, 'phonenumber' => $phonenumber_input];
            $model = '\App\\'.$db;
            $user_id = ($model::where($searches)->first())->$user_id_nom;
        }
        catch (\Throwable $e) {
            $content = 'EmailPhonenumberMatchNotFound';
            $status = 200;
            return response($content, $status);
            exit();
        }
        //if found generate email key
        $email_key = bin2hex(random_bytes(4));
        $email_key_hash = password_hash($email_key, PASSWORD_BCRYPT);

        //if found generate sms key
        $sms_key = bin2hex(random_bytes(4));
        $sms_key_hash = password_hash($sms_key, PASSWORD_BCRYPT);

        //store record
        try {
            $reset_record = new CredentialReset;
            $reset_record->user_id = $user_id;
            $reset_record->user_type = $user_type;
            $reset_record->credential_type = $credential_type;
            $reset_record->email_key_hash = $email_key_hash;
            $reset_record->sms_key_hash = $sms_key_hash;
            $reset_record->verified_state = 0;
            $reset_record->save();
        }
        catch (\Throwable $e) {
            $content = 'ErrorStoringCodes';
            $status = 200;
            return response($content, $status);
            exit();
        }

        //send codes to email and sms
        $emailpack = json_encode(array(
            'to_email' => $email_input,
            'title' => 'Verification Code.',
            'message' => $email_key
        ));
        $this->sendemail($emailpack);

        $smspack = json_encode(array(
            'to_phonenumber' => $phonenumber_input,
            'title' => 'Verification Code.',
            'message' => $sms_key
        ));
        $this->sendsms($smspack);

        //return complete
        $content = 'VerificationCodesSent';
        $status = 200;
        return response($content, $status);
        exit();
    }

    public function CustomerRegister(Request $request) {

        $user = new Customer;
        $user_id = uniqid();
        $user->customer_id = $user_id;
        $user->fname = $request->input('fname');
        $user->lname = $request->input('lname');
        $user->date_of_birth = $request->input('date_of_birth');
        $user->gender = $request->input('gender');
        $user->pin = password_hash($request->input('pin'), PASSWORD_BCRYPT);
        $user->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $user->status = "unverified";

        //check if phonenumber is already in use
        $input_phonenumber = $request->input('phonenumber');
        $check_phonenumber = Customer::where('phonenumber', $input_phonenumber)->first();
        if(!empty($check_phonenumber)){
            $content = 'FoundPhonenumber';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else if(empty($check_phonenumber)){
            //check if email is already in use
            $input_email = $request->input('email');
            $check_email = Customer::where('email', $input_email)->first();
            if(!empty($check_email)){
                $content = 'FoundEmail';
                $status = 200;
                return response($content, $status);
                exit();
            }
            else if(empty($check_email)){
                $user->email = $input_email;
            }
            $user->phonenumber = $input_phonenumber;
        }

        //generate and store token code
        $token = new Token;
        $token->user_id = $user_id;
        $token_to_hash = bin2hex(random_bytes(4));
        $token->token_hash = password_hash($token_to_hash, PASSWORD_BCRYPT);

        $emailpack = json_encode(array(
            'to_email' => $input_email,
            'title' => 'Verification Code.',
            'message' => $token_to_hash
        ));
        $this->sendemail($emailpack);

        $smspack = json_encode(array(
            'to_phonenumber' => $input_phonenumber,
            'title' => 'Verification Code.',
            'message' => $token_to_hash
        ));
        $this->sendsms($smspack);

        //Save token
        $token->save();

        $this->generateemptywallets($user_id);

        //save user
        if($user->save()) {
            $content = 'Success';
            $status = 201;
            return response($content, $status);
        }
        else{
            $content = 'ErrorAdding';
            $status = 200;
            return response($content, $status);
        }
    }

    public function CustomerLogin(Request $request) {

        $emailinput = $request->input('email');
        $passwordinput = $request->input('password');
        $row = Customer::where('email', $emailinput)->first();

        try{
            $findrowpass = $row->password;
            $customer_id = $row->customer_id;
        }
        catch (\Throwable $e) {
            $content = 'EmailNotFound';
            $status = 200;
            return response($content, $status);
            exit();
        }

        $block_types = ['blacklist', 'adminlock', 'finlock'];
        foreach ($block_types as $block_type) {
            if($row->status == $block_type){
                if($row->status == 'adminlock') {
                    $content = 'adminlocked';
                    $status = 200;
                    return response($content, $status);
                    exit();
                }
            }
            else{
                //pass
            }
        }

        if (password_verify($request->input('password'), $findrowpass)){
            $session_pack = json_encode(array(
                'user_id' => $customer_id,
                'client' => $request->input('client')
            ));
            $startsession = $this->newsession($session_pack);
            $session_data = json_decode($startsession);
            $row->session_id = $session_data->session_id;
            $row->token = $session_data->token;
            $content = $row;
            $status = 200;
            return response($content, $status);
            exit();
        }
        else{
            $content = 'InvalidPasswordEmailMatch';
            $status = 200;
            return response($content, $status);
        }
    }

    public function BusinessRegister(Request $request) {

        $business = new Business;
        $business_id = uniqid();
        $business->business_id = $business_id;
        $business->name = $request->input('name');
        $business->branch = $request->input('branch');
        $business->location = $request->input('location');
        $business->pin = password_hash($request->input('pin'), PASSWORD_BCRYPT);
        $business->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $business->status = "unverified";

        //check if phonenumber is already in use
        $input_phonenumber = $request->input('phonenumber');
        $check_phonenumber = Business::where('phonenumber', $input_phonenumber)->first();
        if(!empty($check_phonenumber)){
            $content = 'FoundPhonenumber';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else if(empty($check_phonenumber)){
            //check if email is already in use
            $input_email = $request->input('email');
            $check_email = Business::where('email', $input_email)->first();
            if(!empty($check_email)){
                $content = 'FoundEmail';
                $status = 200;
                return response($content, $status);
                exit();
            }
            else if(empty($check_email)){
                $business->email = $input_email;
            }
            $business->phonenumber = $input_phonenumber;
        }

        //generate and store token code
        $token = new Token;
        $token->user_id = $business_id;
        $token_to_hash = bin2hex(random_bytes(4));
        $token->token_hash = password_hash($token_to_hash, PASSWORD_BCRYPT);

        //check mode, dev or deployment

        //email sms tokens
        $emailpack = json_encode(array(
            'to_email' => $input_email,
            'title' => 'Verification Code.',
            'message' => $token_to_hash
        ));
        $this->sendemail($emailpack);

        $smspack = json_encode(array(
            'to_phonenumber' => $input_phonenumber,
            'title' => 'Verification Code.',
            'message' => $token_to_hash
        ));
        $this->sendsms($smspack);

        //Save token
        $token->save();

        //generate work wallet
        $this->generateworkwallet($business_id);

        //save user
        if($business->save()) {
            $content = 'Success';
            $status = 201;
            return response($content, $status);
        }
        else{
            $content = 'ErrorAdding';
            $status = 200;
            return response($content, $status);
        }
    }

    public function BusinessLogin(Request $request) {
        
        $emailinput = $request->input('email');
        $passwordinput = $request->input('password');
        $row = Business::where('email', $emailinput)->first();

        try{
            $findrowpass = $row->password;
            $business_id = $row->business_id;
        }
        catch (\Throwable $e) {
            $content = 'EmailNotFound';
            $status = 404;
            return response($content, $status);
            exit();
        }

        $block_types = ['blacklist', 'adminlock', 'finlock'];
        foreach ($block_types as $block_type) {
            if($row->status == $block_type){
                if($row->status == 'adminlock') {
                    $content = 'adminlocked';
                    $status = 200;
                    return response($content, $status);
                    exit();
                }
            }
            else{
                //pass
            }
        }

        if (password_verify($request->input('password'), $findrowpass)){
            $session_pack = json_encode(array(
                'user_id' => $business_id,
                'client' => $request->input('client')
            ));
            $startsession = $this->newsession($session_pack);
            $session_data = json_decode($startsession);
            $row->session_id = $session_data->session_id;
            $row->token = $session_data->token;
            $content = $row;
            $status = 200;
            return response($content, $status);
            exit();
        }
        else{
            $content = 'InvalidPasswordEmailMatch';
            $status = 200;
            return response($content, $status);
        }
          
    }

    public function AdminRegister(Request $request) {

        $admin = new Admin;
        $admin_id = uniqid();
        $admin->admin_id = $admin_id;
        $admin->fname = $request->input('fname');
        $admin->lname = $request->input('lname');
        $admin->gender = $request->input('gender');
        $admin->date_of_birth = $request->input('date_of_birth');
        $admin->gender = $request->input('gender');
        $admin->password = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $admin->status = "unverified";
        $admin->level = "user";

        //check if phonenumber is already in use
        $input_phonenumber = $request->input('phonenumber');
        $check_phonenumber = Admin::where('phonenumber', $input_phonenumber)->first();
        if(!empty($check_phonenumber)){
            $content = 'FoundPhonenumber';
            $status = 200;
            return response($content, $status);
            exit();
        }
        else if(empty($check_phonenumber)){
            //check if email is already in use
            $input_email = $request->input('email');
            $check_email = Admin::where('email', $input_email)->first();
            if(!empty($check_email)){
                $content = 'FoundEmail';
                $status = 200;
                return response($content, $status);
                exit();
            }
            else if(empty($check_email)){
                $admin->email = $input_email;
            }
            $admin->phonenumber = $input_phonenumber;
        }

        //generate and store token code
        $token = new Token;
        $token->user_id = $admin_id;
        $token_to_hash = bin2hex(random_bytes(4));
        $token->token_hash = password_hash($token_to_hash, PASSWORD_BCRYPT);

        //check mode, dev or deployment

        //send email and sms
        $emailpack = json_encode(array(
            'to_email' => $input_email,
            'title' => 'Verification Code.',
            'message' => $token_to_hash
        ));
        $this->sendemail($emailpack);

        $smspack = json_encode(array(
            'to_phonenumber' => $input_phonenumber,
            'title' => 'Verification Code.',
            'message' => $token_to_hash
        ));
        $this->sendsms($smspack);

        //Save token
        $token->save();


        //save user
        if($admin->save()) {
            $content = 'Success';
            $status = 201;
            return response($content, $status);
        }
        else{
            $content = 'ErrorAdding';
            $status = 200;
            return response($content, $status);
        }
    }

    public function AdminLogin(Request $request) {

        $emailinput = $request->input('email');
        $passwordinput = $request->input('password');
        $row = Admin::where('email', $emailinput)->first();

        try{
            $findrowpass = $row->password;
            $admin_id = $row->admin_id;
        }
        catch (\Throwable $e) {
            $content = 'Error starting session.';
            $status = 404;
            return response($content, $status);
            exit();
        }

        $block_types = ['blacklist', 'adminlock', 'finlock'];
        foreach ($block_types as $block_type) {
            if($row->status == $block_type){
                if($row->status == 'adminlock') {
                    $content = 'adminlocked';
                    $status = 200;
                    return response($content, $status);
                    exit();
                }
            }
            else{
                //pass
            }
        }

        if (password_verify($request->input('password'), $findrowpass)){
            $session_pack = json_encode(array(
                'user_id' => $admin_id,
                'client' => $request->input('client')
            ));
            $startsession = $this->newsession($session_pack);
            $session_data = json_decode($startsession);
            $row->session_id = $session_data->session_id;
            $row->token = $session_data->token;
            $content = $row;
            $status = 200;
            return response($content, $status);
            exit();
        }
        else{
            $content = 'InvalidPasswordEmailMatch';
            $status = 200;
            return response($content, $status);
        }
    }

    /*
    public function addrate(Request $request){
        $rate_name = $request->input('rate_name');
        $perc_rate = $request->input('percentage_rate');

        $rate = new Rate;
        $rate->rate_name = $rate_name;
        $rate->percentage_rate = $perc_rate;
        $rate->save();
    }*/

    public function newmmacc(Request $request){
        $phonenumber = $request->input('phonenumber');
        $pin = $request->input('pin');

        $MM = new TestMM;
        $MM->phonenumber_id = uniqid();
        $MM->phonenumber = $phonenumber;
        $MM->amount = 10000000;
        $MM->pin = password_hash($pin, PASSWORD_BCRYPT);
        $MM->save();

        $content = 'Account created';
        $status = 200;
        return response($content, $status);
    }
}
