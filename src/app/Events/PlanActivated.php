<?php

namespace HulkApps\AppManager\app\Events;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;

class PlanActivated
{
    use Dispatchable, SerializesModels;

    public $plan;

    public $charge;

    public $previousCharge;

    public function __construct($plan, $charge, $previousCharge = null)
    {
        $this->plan = $plan;
        $this->charge = $charge;
        $this->previousCharge = $previousCharge;
    }
}