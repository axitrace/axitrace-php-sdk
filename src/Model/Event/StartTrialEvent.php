<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * Start trial event.
 *
 * Tracks when a user starts a trial subscription.
 */
class StartTrialEvent extends AbstractEvent
{
    /**
     * @var string
     */
    private string $planName;

    /**
     * @var int|null
     */
    private ?int $trialPeriodDays = null;

    /**
     * @var float|null
     */
    private ?float $trialValue = null;

    /**
     * @var string|null
     */
    private ?string $trialCurrency = null;

    /**
     * @var float|null
     */
    private ?float $predictedLtv = null;

    /**
     * @var string|null
     */
    private ?string $email = null;

    /**
     * @param string $planName
     */
    public function __construct(string $planName)
    {
        $this->planName = $planName;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/start_trial';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'start_trial';
    }

    /**
     * Set trial period in days.
     *
     * @param int $days
     * @return self
     */
    public function setTrialPeriodDays(int $days): self
    {
        $this->trialPeriodDays = $days;
        return $this;
    }

    /**
     * Set trial value.
     *
     * @param float $value
     * @param string $currency
     * @return self
     */
    public function setTrialValue(float $value, string $currency): self
    {
        $this->trialValue = $value;
        $this->trialCurrency = strtoupper($currency);
        return $this;
    }

    /**
     * Set predicted lifetime value.
     *
     * @param float $ltv
     * @return self
     */
    public function setPredictedLtv(float $ltv): self
    {
        $this->predictedLtv = $ltv;
        return $this;
    }

    /**
     * Set user email.
     *
     * @param string $email
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();

        if (empty($this->planName)) {
            throw ValidationException::missingRequiredField('plan_name', 'start_trial');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $data = $this->buildBaseArray();

        $data['plan_name'] = $this->planName;

        if ($this->trialPeriodDays !== null) {
            $data['trial_period_days'] = $this->trialPeriodDays;
        }

        if ($this->trialValue !== null) {
            $data['trial_value'] = $this->trialValue;
        }

        if ($this->trialCurrency !== null) {
            $data['trial_currency'] = $this->trialCurrency;
        }

        if ($this->predictedLtv !== null) {
            $data['predicted_ltv'] = $this->predictedLtv;
        }

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        return $this->addParamsToArray($data);
    }
}
