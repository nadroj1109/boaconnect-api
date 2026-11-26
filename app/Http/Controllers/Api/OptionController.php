<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Enums\BloodType;
use App\Enums\CivilStatus;
use App\Enums\Complexion;
use App\Enums\Gender;
use App\Enums\GovtAssistinceProgram;
use App\Enums\HighestEducationalAttainment;
use App\Enums\HouseholdRelationship;
use App\Enums\HouseholdType;
use App\Enums\HouseholdUnit;
use App\Enums\Nationality;
use App\Enums\ReasonForLeaving;
use App\Enums\ReasonForTransferring;
use App\Enums\Religion;
// use App\Enums\Sector;
use App\Enums\Sex;
use App\Enums\SourceOfIncome;
use App\Enums\TenureStatus;
use App\Enums\TypeResident;
use App\Enums\YesNo;
use App\Models\Barangay;
use App\Models\ComplaintType;
use App\Models\OfficialPosition;
use App\Models\SitioPurok;
use App\Models\Ethnicity;
use App\Models\Role;
use App\Models\Sector;

class OptionController extends Controller
{
    public function index()
    {
        return [
            'blood_types' => BloodType::options(),
            'civil_statuses' => CivilStatus::options(),
            'complexions' => Complexion::options(),
            'genders' => Gender::options(),
            'govt_asst_programs' => GovtAssistinceProgram::options(),
            'educational_attainments' => HighestEducationalAttainment::options(),
            'household_relationships' => HouseholdRelationship::options(),
            'household_types' => HouseholdType::options(),
            'household_units' => HouseholdUnit::options(),
            'nationalities' => Nationality::options(),
            'reasons_for_leaving' => ReasonForLeaving::options(),
            'reasons_for_transferring' => ReasonForTransferring::options(),
            'religions' => Religion::options(),
            // 'sectors' => Sector::options(),
            'sex' => Sex::options(),
            'source_of_income' => SourceOfIncome::options(),
            'tenure_statuses' => TenureStatus::options(),
            'type_residents' => TypeResident::options(),
            'yes_no' => YesNo::options(
                fn($item) => $item === YesNo::Yes
            ),
        ];
    }

    public function barangays()
    {
        $barangays = Barangay::where('city_id', 599)
            ->orderBy('barangay')
            ->get()
            ->map(fn ($barangay) => [
                'value' => $barangay->id,
                'name' => $barangay->barangay
            ]);

        return response()->json($barangays);
    }

    public function sitioPuroks()
    {
        $sitioPuroks = SitioPurok::where('barangay_id', Auth::user()->barangay_id)
            ->where('is_active', 1)
            ->orderBy('sitio_purok')
            ->get()
            ->map(fn ($sitioPuroks) => [
                'value' => $sitioPuroks->id,
                'name' => $sitioPuroks->sitio_purok
            ]);

        return response()->json($sitioPuroks);
    }

    public function registrationSitioPuroks(Request $request)
    {
        $validated = $request->validate([
            'barangay_id' => ['required', 'integer', 'exists:barangays,id'],
        ]);

        $sitioPuroks = SitioPurok::query()
            ->where('barangay_id', $validated['barangay_id'])
            ->where('is_active', true)
            ->orderBy('sitio_purok')
            ->get()
            ->map(fn ($sitioPurok) => [
                'value' => $sitioPurok->id,
                'name' => $sitioPurok->sitio_purok,
            ]);

        return response()->json($sitioPuroks);
    }

    public function ethnicities()
    {
        $ethnicities = Ethnicity::orderBy('ethnicity')
            ->get()
            ->map(fn ($ethnicity) => [
                'value' => $ethnicity->id,
                'name' => $ethnicity->ethnicity
            ]);

        return response()->json($ethnicities);
    }

    public function getRoles()
    {
        $roles = Role::where('id', '<>', 1)
            ->where('id', '<>', 6)
            ->orderBy('id')
            ->get()
            ->map(fn ($role) => [
                'value' => $role->id,
                'name' => $role->name
            ]);

        return response()->json($roles);
    }

    public function getSectors()
    {
        $sectors = Sector::orderBy('sort_order')
            ->get()
            ->map(fn ($sector) => [
                'value' => $sector->id,
                'name' => $sector->sector,
                'code' => $sector->sector_code,
                'is_registrable' => $sector->is_registrable,
            ]);

        return response()->json($sectors);
    }

    public function getComplaintTypes()
    {
        $complaintTypes = ComplaintType::orderBy('name')
            ->get()
            ->map(fn ($complaintType) => [
                'value' => $complaintType->id,
                'name' => $complaintType->name
            ]);

        return response()->json($complaintTypes);
    }

    public function getOfficialPositions()
    {
        $officialPositions = OfficialPosition::orderBy('name')
            ->get()
            ->map(fn ($officialPosition) => [
                'value' => $officialPosition->id,
                'name' => $officialPosition->name
            ]);

        return response()->json($officialPositions);
    }
}
