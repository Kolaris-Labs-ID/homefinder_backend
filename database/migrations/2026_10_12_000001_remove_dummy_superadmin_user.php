<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $dummy = DB::table('users')->where('email', 'admin@homefinder.com')->first();
        if ($dummy) {
            $realAdmin = DB::table('users')
                ->where('id', '!=', $dummy->id)
                ->where('role', 'superadmin')
                ->first() 
                ?? DB::table('users')->where('id', '!=', $dummy->id)->first();

            if ($realAdmin) {
                DB::table('listings')->where('user_id', $dummy->id)->update(['user_id' => $realAdmin->id]);
            }

            DB::table('users')->where('id', $dummy->id)->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
