<?php

namespace App\Services;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;

class PSGCService
{
    public function regions()
	{
	    return Region::orderBy('region')->get();
	}

	public function provinces(Region $region)
	{
	    return $region->provinces()->orderBy('province')->get();
	}

	public function cities(Province $province)
	{
	    return $province->cities()->orderBy('city')->get();
	}

	public function regionCities(Region $region)
	{
	    return $region->cities()->orderBy('city')->get();
	}

	public function barangays(City $city)
	{
	    return $city->barangays()->orderBy('barangay')->get();
	}
}