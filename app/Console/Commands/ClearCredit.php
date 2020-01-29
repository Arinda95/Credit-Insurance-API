<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;

use App\Business;
Use App\Customer;
use App\TestEmail;
Use App\TestSms;
Use App\Wallet;
use App\Notification;
Use App\Transaction;
Use App\TestMM;

class ClearCredit extends Command {
    protected $signature = 'Clear:Credit';
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ClearCredit';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Carry out credit clearence";

    public function handle() {
        $today = date('Y-m-d');

        $creditquery = ['type' => 'credit', 'state' => 'approved'];
        $neardebt = Transaction::where($creditquery)->get();

        foreach($neardebt as $nd) {

            $weekbefore = date('Y-m-d', strtotime('-7 days', strtotime($nd->due_by)));
            $threedaysbefore = date('Y-m-d', strtotime('-3 days', strtotime($nd->due_by)));
            $daybefore = date('Y-m-d', strtotime('-1 days', strtotime($nd->due_by)));

            if ($nd->due_by == $today){
                //lock creditor
                $creditor_id = $nd->part_2;
                $creditor = Customer::where('customer_id', $creditor_id)->first();
                $creditor->status = 'finlock';
                $creditor->save();

                //refund business creditor
                if ($nd->insured == 'Y') {
                    //take cash from akellobanker
                    $mm_account = TestMM::where('phonenumber', '0781077344')->first();
                    $mm_account->amount = $mm_account_amount - $nd->total;
                    $mm_account->save();

                    $mm_account = TestMM::where('phonenumber', $creditor->phonenumber)->first();
                    $mm_account->amount = $mm_account_amount + $nd->total;
                    $mm_account->save();

                    $business_id = $nd->part_1;
                    $business = Business::where('', $business_id)->first();
                    $date = date("F jS, Y", strtotime($nd->created_at));

                    //notify business
                    $email = new TestEmail;
                    $email->from_email = 'AkelloBanker';
                    $email->to_email = $business->email;
                    $email->title = 'Insurance Refund';
                    $email->message = 'You have recieved a refund of '.$nd->total.' from Akellobanker for insured credit offered to '.$creditor->fname.' '.$creditor->lname.' on '.$date.'. Thank your for your support.';
                    $email->save();

                    //unload sms pack
                    $smsdata = json_decode($smspack);
                    $sms = new TestSms;
                    $sms->from_number = 'Akellobanker';
                    $sms->to_number = $business->phonenumber;
                    $sms->title = 'Insurance Refund';
                    $sms->message = 'You have recieved a refund of '.$nd->total.' from Akellobanker for insured credit offered to '.$creditor->fname.' '.$creditor->lname.' on '.$date.'. Thank your for your support.';
                    $sms->save();

                    $notification = new Notification;
                    $notification->notification_id = uniqid();
                    $notification->recipient_id = $business_id;
                    $notification->type = 'Refund';
                    $notification->title = 'Insurance Refund';;
                    $notification->body = 'You have recieved a refund of '.$nd->total.' from Akellobanker for insured credit offered to '.$creditor->fname.' '.$creditor->lname.' on '.$date.'.';
                    $notification->post_script = 'Thank your for your support.';
                    $notification->read_state = 'unread';
                    $notification->save();

                    //notify user
                    $email = new TestEmail;
                    $email->from_email = 'AkelloBanker';
                    $email->to_email = $creditor->email;
                    $email->title = 'Disconnection';
                    $email->message = 'You have been blocked from credit transactions due to the failure to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                    $email->save();

                    //unload sms pack
                    $smsdata = json_decode($smspack);
                    $sms = new TestSms;
                    $sms->from_number = 'Disconnection';
                    $sms->to_number = $creditor->phonenumber;
                    $sms->title = 'You have been blocked from credit';
                    $sms->message = 'You have been blocked from credit transactions due to the failure to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                    $sms->save();

                    $notification = new Notification;
                    $notification->notification_id = uniqid();
                    $notification->recipient_id = $creditor_id;
                    $notification->type = 'Disconnection';
                    $notification->title = 'You have been blocked from credit';
                    $notification->body = 'You have been blocked from credit transactions due to the failure to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                    $notification->post_script = 'Please ensure that you pay back your debts on time.';
                    $notification->read_state = 'unread';
                    $notification->save();
                }
                else{
                    //pass
                }
            }
            else if ($nd->due_by == $weekbefore){

                $date = date("F jS, Y", strtotime($nd->created_at));

                $business_id = $nd->part_1;
                $business = Business::where('', $business_id)->first();
                $date = date("F jS, Y", strtotime($nd->created_at));

                //notify business
                $email = new TestEmail;
                $email->from_email = 'AkelloBanker';
                $email->to_email = $creditor->email;
                $email->title = 'Credit Remainder';
                $email->message = 'You have 7 days left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $email->save();

                //unload sms pack
                $smsdata = json_decode($smspack);
                $sms = new TestSms;
                $sms->from_number = 'Akellobanker';
                $sms->to_number = $creditor->phonenumber;
                $sms->title = 'Credit Remainder';
                $sms->message = 'You have 7 days left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $sms->save();

                $notification = new Notification;
                $notification->notification_id = uniqid();
                $notification->recipient_id = $creditor_id;
                $notification->type = 'Remainder';
                $notification->title = 'Credit Remainder';
                $notification->body = 'You have 7 days left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $notification->post_script = 'Please ensure that you pay back your debts on time.';
                $notification->read_state = 'unread';
                $notification->save();
            }
            else if ($nd->due_by == $threedaysbefore){
                $date = date("F jS, Y", strtotime($nd->created_at));

                $business_id = $nd->part_1;
                $business = Business::where('', $business_id)->first();
                $date = date("F jS, Y", strtotime($nd->created_at));

                //notify business
                $email = new TestEmail;
                $email->from_email = 'AkelloBanker';
                $email->to_email = $creditor->email;
                $email->title = 'Credit Remainder';
                $email->message = 'You have 3 days left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $email->save();

                //unload sms pack
                $smsdata = json_decode($smspack);
                $sms = new TestSms;
                $sms->from_number = 'Akellobanker';
                $sms->to_number = $creditor->phonenumber;
                $sms->title = 'Credit Remainder';
                $sms->message = 'You have 3 days left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $sms->save();

                $notification = new Notification;
                $notification->notification_id = uniqid();
                $notification->recipient_id = $creditor_id;
                $notification->type = 'Remainder';
                $notification->title = 'Credit Remainder';
                $notification->body = 'You have 3 days left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $notification->post_script = 'Please ensure that you pay back your debts on time.';
                $notification->read_state = 'unread';
                $notification->save();
            }
            else if ($nd->due_by == $daybefore){
                $date = date("F jS, Y", strtotime($nd->created_at));

                $business_id = $nd->part_1;
                $business = Business::where('', $business_id)->first();
                $date = date("F jS, Y", strtotime($nd->created_at));

                //notify business
                $email = new TestEmail;
                $email->from_email = 'AkelloBanker';
                $email->to_email = $creditor->email;
                $email->title = 'Credit Remainder';
                $email->message = 'You have 24 hours left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $email->save();

                //unload sms pack
                $smsdata = json_decode($smspack);
                $sms = new TestSms;
                $sms->from_number = 'Akellobanker';
                $sms->to_number = $creditor->phonenumber;
                $sms->title = 'Credit Remainder';
                $sms->message = 'You have 24 hours left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $sms->save();

                $notification = new Notification;
                $notification->notification_id = uniqid();
                $notification->recipient_id = $creditor_id;
                $notification->type = 'Remainder';
                $notification->title = 'Credit Remainder';
                $notification->body = 'You have 24 hours left to pay back credit of '.$nd->total.' to '.$business->name.' for credit offered on '.$date.'.';
                $notification->post_script = 'Please ensure that you pay back your debts on time.';
                $notification->read_state = 'unread';
                $notification->save();
            }
        }
    }
}