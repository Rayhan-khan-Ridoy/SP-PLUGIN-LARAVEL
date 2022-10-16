<?php

namespace Shurjopayv3\SpPluginLaravel\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;


class ShurjopayController extends Controller {


    /**
     * Create token to authorize the merchant
     *
     * @return  mixed $response
     */

    public function authenticate() {
        $merchant_username = env('MERCHANT_USERNAME');
        $merchant_password = env('MERCHANT_PASSWORD');
        $engine_url = env('ENGINE_URL').'/api/get_token';
        $curl_header = array('Content-Type: application/json');

        $payload = array('username' => $merchant_username,'password' => $merchant_password);

        try {
            $response = $this->prepareCurlRequest($engine_url,'POST',json_encode($payload),$curl_header);

            log::channel('shujopay')->info("ShurjoPay has been authenticated successfully.");
            return $response;

        } catch (Exception $e) {
            return $e->getMessage();
        }

    }


    /**
     * Make Payment request to shurjoPay
     *
     * @param  mixed $request
     * @return void
     */

    public function makePayment(Request $request){

        $validation_status = $this->validateInput($request);

        # When validation success
        if($validation_status["isValidationPass"]){

            $merchant_prefix = env('MERCHANT_PREFIX');
            $merchant_return_url = env('MERCHANT_RETURN_URL');
            $merchant_cancel_url = env('MERCHANT_CANCEL_URL');
            $secret_pay_url = env('ENGINE_URL').'/api/secret-pay';
            $curl_header = array('Content-Type: application/json');

            $trxn_data =  $this->prepareTransactionPayload($request);
            $authentication_data = $this->authenticate();
            $response = json_decode($authentication_data);

            if(!empty($response->sp_code) && ($response->sp_code)=='200'){

                $merchant_info = array(
                    'token' => $response->token,
                    'store_id' => $response->store_id,
                    'prefix' => $merchant_prefix,
                    'return_url' => $merchant_return_url,
                    'cancel_url' => $merchant_cancel_url,
                );

                try {
                    $response = $this->prepareCurlRequest($secret_pay_url,'POST',json_encode(array_merge($merchant_info, $trxn_data)),$curl_header);
                    $data = json_decode($response);

                    if(!empty($data->checkout_url)){

                        return redirect($data->checkout_url);
                        log::channel('shujopay')->info("Payment URL has been generated by shurjoPay");
                    }
                    else{
                        return $response;
                    }

                } catch (Exception $e) {
                    return $e->getMessage();
                }
            }

            # When wrong credentials or empty credentials
            else{
                log::channel('shujopay')->info("Payment request failed");
                return $response;
            }
        }

        # When validation fail
        else{
            return $validation_status;
        }
    }

      /**
     *  Verify the payment request
     *
     * @param mixed $order_id
     *
     * @return mixed $response
     */

    public function verifyPayment($order_id) {

        $authentication_data = $this->authenticate();
        $response = json_decode($authentication_data);
        $verification_url = env('ENGINE_URL').'/api/verification';

        if(!empty($response->sp_code) && ($response->sp_code)=='200'){

            try {
                $curl_header = array('Authorization:Bearer '.$response->token,'Content-Type: application/json');
                $response = $this->prepareCurlRequest($verification_url,'POST',json_encode(array('order_id' => $order_id)),$curl_header);

                log::channel('shujopay')->info("Payment verification is done successfully!");
                return $response;

            } catch (Exception $e) {
                return $e->getMessage();
            }

        }

        else{
            log::channel('shujopay')->info("Payment verification is failed!");
            return $response;
        }

    }




    ################################################################################
                   # Service Method
    ################################################################################


     /**
     * Prepare Curl Request
     *
     * @param  mixed $url
     * @param  mixed $method
     * @param  mixed $payload_data
     * @return mixed $response
     */

    public function prepareCurlRequest($url, $method, $payload_data,$header)
    {
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $payload_data,
            CURLOPT_HTTPHEADER => $header
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;

    }

    /**
     * Validate a transaction request required data
     *
     * @param  mixed $request
     * @return void
     */

    public function validateInput($request)
    {
        $validate_input = Validator::make($request->all(), [
            'order_id' => "required|string",
            'amount' => "required",
            'customer_name' => "required",
            'customer_phone' => "required",
            'customer_address' => "required",
            'currency' => "required",
            'client_ip' =>"required"
        ]);

        # If validation fails show appropriate errors
        if ($validate_input->fails()) {
            $errors = $validate_input->errors();

            $error_array = array();
            foreach ($errors->all() as $error){
                $e = $error;
                array_push($error_array,$e);
            }

            return array(
                'isValidationPass' => false,
                'message' => "Validation Failed",
                'errors' => array($error_array)
            );
        }

        # When validation passed
        else{
            return array(
                'isValidationPass' => true,
                'message' => "Validation success",
            );
        }

    }

    /**
     * Prepare Transaction Payload
     *
     * @param  mixed $request
     * @return void
     */
    public function prepareTransactionPayload($request)
    {
        $payload_data = array(
            'currency' => $request->currency,
            'amount' => $request->amount,
            'order_id' => $request->order_id,
            'discsount_amount' => $request->discsount_amount ,
            'disc_percent' => $request->disc_percent ,
            'client_ip' => $request->client_ip,
            'customer_name' => $request->customer_name,
            'customer_phone' => $request->customer_phone,
            'email' => $request->email,
            'customer_address' => $request->customer_address,
            'customer_city' => $request->customer_city,
            'customer_state' => $request->customer_state,
            'customer_postcode' => $request->customer_postcode,
            'customer_country' => $request->customer_country,
            'value1' => $request->value1,
            'value2' => $request->value2,
            'value3' => $request->value3,
            'value4' => $request->value4,
        );

        return $payload_data;
    }



}
