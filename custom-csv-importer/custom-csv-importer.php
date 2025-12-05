<?php
/*
Plugin Name: Custom CSV Importer (ACF + CPT)
Description: Import CSV into a Custom Post Type with ACF fields and Unique ID updating.
Version: 2.0
Author: Ahsan
*/

if (!defined('ABSPATH')) exit;

/* ---------------------------------------------
   ADMIN MENU
--------------------------------------------- */
add_action('admin_menu', function () {
    add_menu_page(
        'CSV Importer',
        'CSV Importer',
        'manage_options',
        'custom-csv-importer',
        'cci_page_html'
    );
});

/* ---------------------------------------------
   ADMIN PAGE HTML
--------------------------------------------- */
function cci_page_html()
{
    ?>
    <div class="wrap">
        <h1>Custom CSV Importer</h1>

        <form method="post" enctype="multipart/form-data">
            <p><strong>Select CSV File:</strong></p>
            <input type="file" name="csv_file" required>

            <p><strong>Enter Custom Post Type Slug:</strong></p>
            <input type="text" name="cpt" value="listing" required>

            <br><br>
            <input type="submit" class="button button-primary" value="Import CSV" name="cci_import">
        </form>

        <hr>

        <?php
        if (isset($_POST["cci_import"])) {
            cci_process_csv();
        }
        ?>
    </div>
    <?php
}

/* ---------------------------------------------
   CSV PROCESSOR
--------------------------------------------- */
function cci_process_csv()
{
    if (!isset($_FILES["csv_file"])) {
        echo "<p style='color:red;'>No file selected.</p>";
        return;
    }

    $cpt = sanitize_text_field($_POST["cpt"]); // Your CPT
    $file = $_FILES["csv_file"]["tmp_name"];

    $rows = array_map("str_getcsv", file($file));
    $headers = array_shift($rows);

    echo "<h2>Importing...</h2>";

    foreach ($rows as $row) {
        $data = array_combine($headers, $row);

        // REQUIRED FIELDS
        $post_title = $data["post_title"] ?? "Untitled";
        $unique_id  = $data["unique_id"] ?? null;

        // SEARCH EXISTING POST BY UNIQUE ID
        $existing_id = 0;

        if ($unique_id) {
            $existing = new WP_Query([
                "post_type"      => $cpt,
                "meta_key"       => "unique_id",
                "meta_value"     => $unique_id,
                "posts_per_page" => 1,
            ]);

            if ($existing->have_posts()) {
                $existing_id = $existing->posts[0]->ID;
            }
        }

        // CREATE OR UPDATE
        if ($existing_id) {
            $post_id = $existing_id;
            wp_update_post([
                "ID"         => $post_id,
                "post_title" => $post_title,
            ]);
        } else {
            $post_id = wp_insert_post([
                "post_title"  => $post_title,
                "post_type"   => $cpt,
                "post_status" => "publish",
            ]);
        }

        // SAVE ALL FIELDS INCLUDING ACF
        foreach ($data as $key => $value) {
            if ($key == "post_title") continue;
            if ($key == "unique_id") update_post_meta($post_id, "unique_id", $unique_id);

            if (function_exists("update_field")) {
                update_field($key, $value, $post_id); // ACF
            } else {
                update_post_meta($post_id, $key, $value); // normal custom field
            }
        }

        echo "<p>Imported: <strong>$post_title</strong> (ID: $post_id)</p>";
    }

    echo "<h3 style='color:green;'>Import Finished!</h3>";
}
