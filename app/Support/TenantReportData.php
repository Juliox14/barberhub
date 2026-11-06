<?php

namespace App\Support;

use App\Http\Requests\TenantReportRequest;
use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Barbershop;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class TenantReportData
{
    private readonly CarbonImmutable $fromDate;

    private readonly CarbonImmutable $toDate;

    public function __construct(
        public readonly TenantReportRequest $request,
        public readonly Barbershop $barbershop,
    ) {
        $this->fromDate = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $request->validated('from'),
            $barbershop->timezone,
        );
        $this->toDate = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $request->validated('to'),
            $barbershop->timezone,
        );
    }

    public function from(): CarbonImmutable
    {
        return $this->fromDate;
    }

    public function to(): CarbonImmutable
    {
        return $this->toDate;
    }

    public function endExclusive(): CarbonImmutable
    {
        return $this->toDate->addDay();
    }

    public function periodDays(): int
    {
        return $this->fromDate->diff($this->toDate)->days + 1;
    }

    public function timezone(): string
    {
        return $this->barbershop->timezone;
    }

    public function barberId(): ?int
    {
        $id = $this->request->validated('barber_id');

        return $id === null ? null : (int) $id;
    }

    public function serviceId(): ?int
    {
        $id = $this->request->validated('service_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Appointment DATETIME columns store barbershop wall-clock values; keep these bounds local.
     *
     * @return Builder<Appointment>
     */
    public function query(): Builder
    {
        return $this->barbershop->appointments()->getQuery()
            ->where('starts_at', '>=', $this->fromDate->startOfDay())
            ->where('starts_at', '<', $this->endExclusive()->startOfDay())
            ->when($this->barberId() !== null, fn (Builder $query): Builder => $query->where('barber_id', $this->barberId()))
            ->when($this->serviceId() !== null, fn (Builder $query): Builder => $query->where('service_id', $this->serviceId()));
    }

    /** @return Collection<int, Barber> */
    public function barbers(): Collection
    {
        return $this->barbershop->barbers()->orderBy('display_name')->get();
    }

    /** @return Collection<int, Service> */
    public function services(): Collection
    {
        return $this->barbershop->services()->orderBy('name')->get();
    }
}
