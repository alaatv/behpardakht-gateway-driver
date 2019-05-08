<?php

namespace AlaaTV\BehpardakhtDriver;

use AlaaTV\Gateways\Contracts\OnlinePaymentVerificationResponseInterface;

class VerificationResponse implements OnlinePaymentVerificationResponseInterface
{
    private $response;

    private $success;

    private $status;

    /**
     * VerificationResponse constructor.
     *
     * @param array $response
     * @param $success
     * @param $status
     */
    public function __construct(array $response, $success, $status)
    {
        $this->response = $response;
        $this->success = $success;
        $this->status = $status;
    }
    
    public static function instance($result, $success, $status)
    {
        return new static($result, $success, $status);
    }
    
    public function isVerifiedBefore()
    {
        return $this->getStatus() == 43;
    }
    
    private function getStatus(): string
    {
        return $this->status ?? '';
    }
    
    public function getCardPanMask()
    {
        return array_get($this->response, 'CardHolderPan', '');
    }
    
    /**
     *array:8 [▼
     * "RefId" => "723D241477D96CD1"
     * "ResCode" => "0"
     * "SaleOrderId" => "19"
     * "SaleReferenceId" => "150078823126"
     * "CardHolderInfo" => "DC514292D753D85BD5D91874EBD2A35DD64E8E1EC066A7A2C7D4C9C51BA994C5"
     * "CardHolderPan" => "603799******9276"
     * "FinalAmount" => "1002"
     * ]*/
    
    public function getCardHash()
    {
        return array_get($this->response, 'CardHolderInfo', '');
    }
    
    public function getMessages()
    {
        if ($this->isSuccessfulPayment()) {
            
            $message = ['پرداخت کاربر تایید شد.'];
            if ($this->hasBeenVerifiedBefore()) {
                $message[] = ErrorMsgRepository::getMsg($this->getStatus());
            }
            
            return $message;
        }

        $message = ['خطایی در پرداخت رخ داده است.'];

        $message[] = ErrorMsgRepository::getMsg($this->getStatus());

        return $message;
    }
    
    public function isSuccessfulPayment(): bool
    {
        if (!$this->success) {
            return $this->success;
        }

        return $this->getStatus() == '0' or $this->isVerifiedBefore();
    }
    
    public function getRefId()
    {
        return $this->response['RefId'] ?? '';
    }
    
    public function hasBeenVerifiedBefore(): bool
    {
        return $this->getStatus() == 43;
    }
    
    public function isCanceled(): bool
    {
        return $this->getStatus() == 17;
    }
}