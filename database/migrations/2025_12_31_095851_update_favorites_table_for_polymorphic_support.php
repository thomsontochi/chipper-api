<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $postMorphClass = 'App\\Models\\Post';

        Schema::table('favorites', function (Blueprint $table) {
            $table->unsignedBigInteger('favorable_id')->nullable()->after('id');
            $table->string('favorable_type')->nullable()->after('favorable_id');
        });

        DB::table('favorites')
            ->orderBy('id')
            ->chunkById(100, function ($favorites) use ($postMorphClass) {
                foreach ($favorites as $favorite) {
                    DB::table('favorites')
                        ->where('id', $favorite->id)
                        ->update([
                            'favorable_id' => $favorite->post_id,
                            'favorable_type' => $postMorphClass,
                        ]);
                }
            }, 'id');

        DB::table('favorites')
            ->whereNull('favorable_type')
            ->update(['favorable_type' => $postMorphClass]);

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE favorites MODIFY favorable_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE favorites MODIFY favorable_type VARCHAR(255) NOT NULL');
        }

        Schema::table('favorites', function (Blueprint $table) {
            $table->unique(['user_id', 'favorable_id', 'favorable_type'], 'favorites_favorable_unique');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn('post_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $postMorphClass = 'App\\Models\\Post';

        Schema::table('favorites', function (Blueprint $table) {
            $table->unsignedBigInteger('post_id')->nullable()->after('id');
            $table->dropUnique('favorites_favorable_unique');
        });

        DB::table('favorites')
            ->where('favorable_type', $postMorphClass)
            ->orderBy('id')
            ->chunkById(100, function ($favorites) {
                foreach ($favorites as $favorite) {
                    DB::table('favorites')
                        ->where('id', $favorite->id)
                        ->update([
                            'post_id' => $favorite->favorable_id,
                        ]);
                }
            }, 'id');

        DB::table('favorites')
            ->where('favorable_type', '!=', $postMorphClass)
            ->delete();

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn(['favorable_id', 'favorable_type']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE favorites MODIFY post_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
