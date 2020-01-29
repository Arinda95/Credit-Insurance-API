<?php

$router->get('/', function() use ($router) {
    return "Akellobanker API is live";
});

$router->group(['prefix'=>'/api/v2'], function() use ($router) {

    //open, no auth pack required
    $router->group(['prefix'=>'/open'], function() use ($router) {

        $router->post('/testemails', 'OpenController@TestEmails');
        $router->post('/testsms', 'OpenController@TestSms');

        $router->group(['prefix'=>'/business'], function() use ($router) {
            //registration for business
            $router->post('/register', 'OpenController@BusinessRegister');
            //login for business
            $router->post('/login', 'OpenController@BusinessLogin');

            //user identification.  Email and Phonenumber
            $router->post('/useridentification', 'OpenController@UserIdentification');
            //Token verification. Email token and sms token
            $router->post('/userverification', 'OpenController@UserVerification');
            //Password reset. input new password
            $router->post('/credentialsreset', 'OpenController@CredentialsReset');
        });

        $router->group(['prefix'=>'/customer'], function() use ($router) {
            //registration for customer
            $router->post('/register', 'OpenController@CustomerRegister');
            //login for customer
            $router->post('/login', 'OpenController@CustomerLogin');

            //user identification.  Email and Phonenumber
            $router->post('/useridentification', 'OpenController@UserIdentification');
            //Token verification. Email token and sms token
            $router->post('/userverification', 'OpenController@UserVerification');
            //Password reset. input new password
            $router->post('/credentialsreset', 'OpenController@CredentialsReset');
        });

        $router->group(['prefix'=>'/admin'], function() use ($router) {
            //registration for admin
            $router->post('/register', 'OpenController@AdminRegister');
            //login for admin
            $router->post('/login', 'OpenController@AdminLogin');

            //user identification.  Email and Phonenumber
            $router->post('/useridentification', 'OpenController@UserIdentification');
            //Token verification. Email token and sms token
            $router->post('/userverification', 'OpenController@UserVerification');
            //Password reset. input new password
            $router->post('/credentialsreset', 'OpenController@CredentialsReset');
        });

    });

    //for business users, auth applied
    $router->group(['prefix'=>'/business', 'middleware'=>'UserAuth'], function() use ($router) {
        //token verification
        $router->post('/token', 'BusinessController@tokenverify');
        $router->post('/getstatus', 'BusinessController@statusverify');

        //load work cash
        $router->post('/addworkcash', 'BusinessController@addworkcash');
        //get work cash
        $router->post('/getworkcash', 'BusinessController@getworkcash');

        //point of sale
        $router->post('/sell', 'BusinessController@sell');
        //get notifications
        $router->post('/getnotifications', 'BusinessController@getnotifs');

        //get records
        $router->post('/getrecords', 'BusinessController@getrecords');
        $router->post('/searchrecords', 'BusinessController@searchrecords');

        //get creditors
        $router->post('/getcreditors', 'BusinessController@getcreditors');
        $router->post('/searchcreditors', 'BusinessController@searchcreditors');

        //paid off credit
        $router->post('/paidoff', 'BusinessController@paidoff');

        //get business information
        $router->post('/getbusinessinformation', 'BusinessController@getbizinfo');

        //logout
        $router->post('/logout', 'BusinessController@logout');
    });

    //for customer users, auth applied
    $router->group(['prefix'=>'/customer', 'middleware'=>'UserAuth'], function() use ($router) {
        //token verification
        $router->post('/token', 'CustomerController@tokenverify');
        //user status
        $router->post('/getstatus', 'CustomerController@statusverify');

        //approvals get
        $router->post('/getapprovals', 'CustomerController@getappr');
        //approvals approve
        $router->post('/approve', 'CustomerController@approve');
        //approvals reject
        $router->post('/decline', 'CustomerController@decline');

        //notifications get
        $router->post('/getnotifications', 'CustomerController@getnotifs');

        //wallets get
        $router->post('/getwallets', 'CustomerController@getwallets');

        //records get
        $router->post('/getrecords', 'CustomerController@getrecords');
        $router->post('/searchrecords', 'CustomerController@searchrecords');

        //get all credit
        $router->post('/getcredit', 'CustomerController@getcredit');

        //get phonennumber owners names
        $router->post('/getnamesbynumber', 'CustomerController@getnamesbynumber');

        //credit tools send money to mm
        $router->post('/mm2wallet', 'CustomerController@mm2wallet');
        //credit tools wallet to wallet
        $router->post('/wallet2wallet', 'CustomerController@wallet2wallet');
        //credit tools pay off credit with wallet
        $router->post('/creditpwallet', 'CustomerController@creditPwallet');
        //credit tools pay off credit with mobile money
        $router->post('/creditpmm', 'CustomerController@creditPMM');
        //credit tools withdraw credit
        $router->post('/credit2mm', 'CustomerController@credit2MM');

        //account info get
        $router->post('/getaccountinformation', 'CustomerController@getaccinfo');

        //logout
        $router->post('/logout', 'CustomerController@logout');
    });

    //for admin users, auth applied
    $router->group(['prefix'=>'/admin'], function() use ($router) {

        $router->group(['prefix'=>'/user', 'middleware'=>'UserAuth'], function() use ($router) {
            //token verification
            $router->post('/token', 'AdminController@tokenverify');
            $router->post('/getstatus', 'AdminController@statusverify');
            $router->post('/level', 'AdminController@getlevel');
        });

        $router->group(['prefix'=>'/levelone', 'middleware'=>'AdminAuthOne'], function() use ($router) {
            //customer searh
            $router->post('/searchcustomer', 'AdminController@searchcust');
            //business transactions deep dive
            $router->post('/transactionsdivecustomer', 'AdminController@transactionsdivecustomer');
        });

        $router->group(['prefix'=>'/leveltwo', 'middleware'=>'AdminAuthTwo'], function() use ($router) {
            //business search
            $router->post('/searchbusiness', 'AdminController@searchbiz');
            //business transactions deep dive
            $router->post('/transactionsdivebusiness', 'AdminController@transactionsdivebusiness');
        });

        $router->group(['prefix'=>'/levelthree', 'middleware'=>'AdminAuthThree'], function() use ($router) {
            //fetch access levels
            $router->post('/fetchadminbynumber', 'AdminController@fetchadminbynumber');
            //update transaction rate
            $router->post('/updatetransactionrate', 'AdminController@updatetransactionrate');
            //update policy rate
            $router->post('/updatepolicyrate', 'AdminController@updatepolicyrate');
            //update user
            $router->post('/updatelevel', 'AdminController@updatelevel');

            //get policy rate
            $router->post('/getpolicyrate', 'AdminController@getpolicyrate');
            //get transaction rate
            $router->post('/gettransactionrate', 'AdminController@gettransactionrate');

            //get business by number
            $router->post('/fetchbusinessbynumber', 'AdminController@fetchbusinessbynumber');
            //get customer by number
            $router->post('/fetchcustomerbynumber', 'AdminController@fetchcustomerbynumber');

            //update customer access level
            $router->post('/updatecustomerlevel', 'AdminController@updatecustomerlevel');
            //update business access level
            $router->post('/updatebusinesslevel', 'AdminController@updatebusinesslevel');

        });

        //logout
        $router->post('/logout', 'AdminController@logout');

    });

    //ussd routes :: middlewareless
    $router->group(['prefix' => '/ussd'], function() use ($router) {

        $router->group(['prefix' => '/customer'], function() use ($router) {
            $router->post('/endpoint', 'UssdController@receiver');
        });

        $router->group(['prefix' => '/business'], function() use ($router) {
            $router->post('/endpoint', 'UssdController@receiver');
        });
    });

});
