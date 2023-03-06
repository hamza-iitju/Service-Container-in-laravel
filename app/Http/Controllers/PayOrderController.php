<?php

namespace App\Http\Controllers;

use App\Billing\PaymentGatewayContract;
use App\Orders\OrderDetails;

class PayOrderController extends Controller
{
    public function store(OrderDetails $orderDetails, PaymentGatewayContract $paymentGatewayContract)
    {
        $orderDetails->all();
        dd($paymentGatewayContract->charge(2500));
    }
}
