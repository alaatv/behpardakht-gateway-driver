<?php

namespace AlaaTV\BehpardakhtDriver;

use DateTime;
use AlaaTV\Gateways\RedirectData;
use AlaaTV\Gateways\Contracts\{OnlineGateway, IranianCurrency, OnlinePaymentVerificationResponseInterface};
use Illuminate\Http\Request;

class BehpardakhtGateWay implements OnlineGateway
{
    public function generateAuthorityCode(string $callbackUrl, IranianCurrency $cost, string $description, $orderId = null)
    {
        $dateTime = new DateTime();

        $fields = [
            'terminalId'     => (int) config('behpardakht.terminalId'),
            'userName'       => config('behpardakht.username'),
            'userPassword'   => (int) config('behpardakht.password'),
            'orderId'        => $orderId,
            'amount'         => $cost->rials(),
            'localDate'      => $dateTime->format('Ymd'),
            'localTime'      => $dateTime->format('His'),
            'additionalData' => $description,
            'callBackUrl'    => $callbackUrl,
            'payerId'        => 0,
        ];

        try {
            $response = $this->makeSoapClient()
                ->bpPayRequest($fields);
        } catch (\SoapFault $e) {
            return null;
        }

        $response = explode(',', $response->return);

        if ($response[0] != '0') {
            return null;
        }

        return $response[1];
    }
    
    public function generatePaymentPageUriObject($refId): RedirectData
    {
        $serverUrl = 'https://bpm.shaparak.ir/pgwchannel/startpay.mellat';
        $data      = [
            ['name' => 'RefId', 'value' => $refId,]
        ];
    
        return RedirectData::instance($serverUrl, $data, 'POST');
    }
    
    public function getAuthorityValue(): string
    {
        return Request::input('RefId', '');
    }
    
    public function verifyPayment(IranianCurrency $amount, $authority): OnlinePaymentVerificationResponseInterface
    {
        /**
         *array:8 [▼
         * "RefId" => "723D241477D96CD1"
         * "ResCode" => "0"
         * "SaleOrderId" => "19"
         * "SaleReferenceId" => "150078823126"
         * "CardHolderInfo" => "DC514292D753D85BD5D91874EBD2A35DD64E8E1EC066A7A2C7D4C9C51BA994C5"
         * "CardHolderPan" => "603799******9276"
         * "FinalAmount" => "1002"
         * "_token" => "CATNE2rXs0TWtSh2I4aFNyqpYMOq15cLGwVgcKpD"`
         * ]
         */
//        if ($amount->rials() !== Request::input('FinalAmount')) {
//            return 'fake request.';
//        }
        /*
        $refId             = Request::input('RefId');
        $trackingCode      = Request::input('SaleReferenceId');
        $cardNumber        = Request::input('CardHolderPan');
        $resCode           = Request::input('ResCode');
        $saleOrderId       = Request::input('SaleOrderId');*/

        if (Request::input('ResCode') != 0) {
            return VerificationResponse::instance(request()->all(), false, Request::input('ResCode'));
        }

        $response = $this->verify();

        if ($response->return != '0') {
            return VerificationResponse::instance(request()->all(), false, $response->return);
        }

        $response = $this->settleRequest();

        if ($response->return != '0') {
            return VerificationResponse::instance(request()->all(), false, $response->return);
        }

        return VerificationResponse::instance(request()->all(), true, Request::input('ResCode'));
    }
    
    protected function settleRequest()
    {
        /*
         * enseraf karbar :
        array:4 [▼
            "RefId" => "5925E561FF4B2421"
            "ResCode" => "17"
            "SaleOrderId" => "15"
            "_token" => "CATNE2rXs0TWtSh2I4aFNyqpYMOq15cLGwVgcKpD"
        ]
*/
        try {
            return $this->makeSoapClient()
                ->bpSettleRequest($this->getVerificationParams());
        } catch (\SoapFault $e) {
            throw $e;
        }
    }
    
    /**
     * @return array
     */
    private function getVerificationParams(): array
    {
        return [
            'terminalId'      => config('behpardakht.terminalId'),
            'userName'        => config('behpardakht.username'),
            'userPassword'    => config('behpardakht.password'),
            'orderId'         => Request::input('SaleOrderId'),
            'saleOrderId'     => Request::input('SaleOrderId'),
            'saleReferenceId' => Request::input('SaleReferenceId'),
        ];
    }
    
    /**
     * @return \SoapClient
     * @throws \SoapFault
     */
    private function makeSoapClient(): \SoapClient
    {
        return new \SoapClient('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
    }
    
    /**
     * @return mixed
     * @throws \SoapFault
     */
    private function verify()
    {
        try {
            return $this->makeSoapClient()
                ->bpVerifyRequest($this->getVerificationParams());
        } catch (\SoapFault $e) {
            throw $e;
        }
    }
}
