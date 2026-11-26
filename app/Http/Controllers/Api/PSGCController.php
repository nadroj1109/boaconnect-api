<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegionResource;
use App\Http\Resources\ProvinceResource;
use App\Http\Resources\CityResource;
use App\Http\Resources\BarangayResource;
use App\Models\Region;
use App\Models\Province;
use App\Models\City;
use App\Services\PSGCService;

class PSGCController extends Controller
{
    public function __construct(
        protected PSGCService $service
    ) {}

    public function regions()
    {
        return RegionResource::collection(
            $this->service->regions()
        );
    }

    public function provinces(Region $region)
    {
        return ProvinceResource::collection(
            $this->service->provinces($region)
        );
    }

    public function cities(Province $province)
    {
        return CityResource::collection(
            $this->service->cities($province)
        );
    }

    public function regionCities(Region $region)
    {
        return CityResource::collection(
            $this->service->regionCities($region)
        );
    }

    public function barangays(City $city)
    {
        return BarangayResource::collection(
            $this->service->barangays($city)
        );
    }
}
