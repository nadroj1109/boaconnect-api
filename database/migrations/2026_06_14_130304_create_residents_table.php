<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->integer('household_id');
            $table->boolean('is_household_head');
            $table->string('type_of_resident', 50);
            $table->string('philsys_card_no', 50)->nullable();
            $table->string('last_name', 150);
            $table->string('first_name', 150);
            $table->string('middle_name', 150)->nullable();
            $table->string('ext_name', 50)->nullable();
            $table->date('date_of_birth', 50);
            $table->decimal('age', 3, 2)->default(0);
            $table->string('place_of_birth', 150);
            $table->string('res_mother_upon_birth', 150)->nullable();
            $table->enum('sex', ['male', 'female']);
            $table->string('gender', 50)->nullable();
            $table->string('civil_status', 50);
            $table->boolean('pregnant_woman')->default(false);
            $table->string('highest_educ_attainment', 100);
            $table->string('profession_occupation', 100)->nullable();
            $table->string('email_address', 150)->unique()->nullable();
            $table->string('mobile_no', 20)->nullable();
            $table->string('tel_no', 20)->nullable();
            $table->integer('barangay_id');
            $table->string('sitio_purok', 50)->nullable();
            $table->string('house_no', 50)->nullable();
            $table->string('street_name', 100)->nullable();
            $table->string('subdivision', 100)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->char('blood_type', 10)->nullable();
            $table->decimal('height', 5, 2)->default(0);
            $table->decimal('weight', 5, 2)->default(0);
            $table->enum('complexion', ['fair', 'medium', 'dark'])->nullable();
            $table->string('nationality', 50);
            $table->boolean('registered_voter')->default(false);
            $table->boolean('resident_voter')->default(false);
            $table->smallInteger('last_voted_yr');
            $table->integer('ethnicity_id')->nullable();
            $table->string('religion', 50);
            $table->string('religion_other', 150)->nullable();
            $table->string('mother_fname', 150)->nullable();
            $table->string('mother_midname', 150)->nullable();
            $table->string('mother_lname', 150)->nullable();
            $table->string('govt_asst_progam', 50)->nullable();
            $table->string('govt_asst_progam_other', 50)->nullable();
            $table->string('relationship_to_hh_head', 100)->nullable();
            $table->string('source_of_income', 100)->nullable();
            $table->string('source_of_income_other', 150)->nullable();
            $table->decimal('monthly_income', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('residents');
    }
};
