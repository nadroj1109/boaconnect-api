<?php

namespace App\Console\Commands;

use App\Models\Barangay;
use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPSGC extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'psgc:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import Philippine Standard Geographic Code (PSGC)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $file = storage_path('app/psgc.xlsx');

        if (! file_exists($file)) {

            $this->error("storage/app/psgc.xlsx not found.");

            return self::FAILURE;

        }

        $this->info('Reading PSGC Excel...');

        $spreadsheet = IOFactory::load($file);

        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray();

        if (count($rows) <= 1) {

            $this->error('Excel contains no data.');

            return self::FAILURE;

        }

        /*
        |--------------------------------------------------------------------------
        | Detect Header Columns
        |--------------------------------------------------------------------------
        */

        $headers = array_shift($rows);

        $headers = array_map(fn ($value) => trim((string) $value), $headers);

        $columns = array_flip($headers);

        $required = [

            '10-digit PSGC',

            'Name',

            'Geographic Level',

        ];

        foreach ($required as $column) {

            if (! isset($columns[$column])) {

                $this->error("Missing column: {$column}");

                return self::FAILURE;

            }

        }

        DB::beginTransaction();

        try {

            $this->info("Clearing old PSGC...");

            // DB::statement('SET FOREIGN_KEY_CHECKS=0;');

            // Barangay::truncate();
            // City::truncate();
            // Province::truncate();
            // Region::truncate();

            // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            $currentRegion = null;

            $currentProvince = null;

            $currentCity = null;

            $regionCount = 0;

            $provinceCount = 0;

            $cityCount = 0;

            $barangayCount = 0;

            $this->info("Importing...");

            foreach ($rows as $row) {

                $psgcCode = trim((string) $row[$columns['10-digit PSGC']]);

                $name = trim((string) $row[$columns['Name']]);

                $level = trim((string) $row[$columns['Geographic Level']]);

                if ($psgcCode == '' || $level == '') {

                    continue;

                }

                $this->line($level . ' - ' . $name);

                switch ($level) {

                    /*
                    |--------------------------------------------------------------------------
                    | Region
                    |--------------------------------------------------------------------------
                    */

                    case 'Reg':

                        $currentRegion = Region::create([

                            'psgc_code' => $psgcCode,

                            'region' => $name,

                        ]);

                        $currentProvince = null;

                        $currentCity = null;

                        $regionCount++;

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Province
                    |--------------------------------------------------------------------------
                    */

                    case 'Prov':

                        if (!$currentRegion) {

                            throw new \Exception("Province {$name} has no Region.");

                        }

                        $currentProvince = Province::create([

                            'region_id' => $currentRegion->id,

                            'psgc_code' => $psgcCode,

                            'province' => $name,

                        ]);

                        $currentCity = null;

                        $provinceCount++;

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | City / Municipality
                    |--------------------------------------------------------------------------
                    */

                    case 'City':

                    case 'Mun':

                    case 'SubMun':

                        if (!$currentRegion) {

                            throw new \Exception("City {$name} has no Region.");

                        }

                        $currentCity = City::create([

                            'region_id' => $currentRegion->id,

                            'province_id' => $currentProvince?->id,

                            'psgc_code' => $psgcCode,

                            'city' => $name,

                            'is_city' => $level === 'City',

                        ]);

                        $cityCount++;

                        break;

                    /*
                    |--------------------------------------------------------------------------
                    | Barangay
                    |--------------------------------------------------------------------------
                    */

                    case 'Bgy':

                        if (!$currentCity) {

                            throw new \Exception("Barangay {$name} has no City.");

                        }

                        Barangay::create([

                            'city_id' => $currentCity->id,

                            'psgc_code' => $psgcCode,

                            'barangay' => $name,

                        ]);

                        $barangayCount++;

                        break;
                }
            }

            DB::commit();

            $this->newLine();

            $this->info("======================================");
            $this->info(" PSGC Imported Successfully");
            $this->info("======================================");

            $this->table(

                ['Table', 'Imported'],

                [

                    ['Regions', $regionCount],

                    ['Provinces', $provinceCount],

                    ['Cities/Municipalities', $cityCount],

                    ['Barangays', $barangayCount],

                ]

            );

            return self::SUCCESS;

        } catch (\Throwable $e) {

            // DB::statement('SET FOREIGN_KEY_CHECKS=1;');

            DB::rollBack();

            $this->error($e->getMessage());

            return self::FAILURE;

        }
    }
}
