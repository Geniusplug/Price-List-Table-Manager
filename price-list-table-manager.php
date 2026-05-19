<?php
/**
 * Plugin Name: Price List Table Manager
 * Description: Upload Excel/CSV files and display searchable, paginated responsive tables with shortcode and PDF watermark export.
 * Version: 1.0.1
 * Author: Md ROman Sarkar
 * License: Apache License 2.0
 * Text Domain: price-list-table-manager
 * Domain Path: https://geniusplug.com
 * Requires PHP: 5.7
 * Requires at least: 5.0
 */

if (!defined('ABSPATH')) exit;

define('PLTM_VERSION', '1.0.1');
define('PLTM_PLUGIN_FILE', __FILE__);
define('PLTM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PLTM_PLUGIN_URL', plugin_dir_url(__FILE__));

class PLTM_Plugin {
    private static $instance = null;
    private $table;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'pltm_tables';
        register_activation_hook(__FILE__, array($this, 'activate'));
        add_action('init', array($this, 'maybe_create_table'));
        add_action('admin_init', array($this, 'maybe_create_table'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'front_assets'));
        add_shortcode('price_list_table', array($this, 'shortcode'));
        add_action('admin_post_pltm_upload', array($this, 'handle_upload'));
        add_action('admin_post_pltm_delete', array($this, 'handle_delete'));
        add_action('admin_post_nopriv_pltm_export_pdf', array($this, 'export_pdf'));
        add_action('admin_post_pltm_export_pdf', array($this, 'export_pdf'));
        add_action('admin_post_nopriv_pltm_export_csv', array($this, 'export_csv'));
        add_action('admin_post_pltm_export_csv', array($this, 'export_csv'));
    }

    public function activate() {
        $this->maybe_create_table();
    }

    public function maybe_create_table() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        // Avoid reserved SQL names such as ROWS by using row_data.
        $sql = "CREATE TABLE {$this->table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            headers LONGTEXT NOT NULL,
            row_data LONGTEXT NOT NULL,
            layout VARCHAR(30) NOT NULL DEFAULT 'modern',
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";
        dbDelta($sql);
    }

    public function admin_menu() {
        add_menu_page('Price Tables', 'Price Tables', 'manage_options', 'pltm', array($this, 'admin_page'), 'dashicons-media-spreadsheet', 26);
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'pltm') === false) return;
        wp_enqueue_style('pltm-admin', PLTM_PLUGIN_URL . 'assets/pltm.css', array(), PLTM_VERSION);
        wp_enqueue_script('pltm-js', PLTM_PLUGIN_URL . 'assets/pltm.js', array(), PLTM_VERSION, true);
    }

    public function front_assets() {
        wp_enqueue_style('pltm-front', PLTM_PLUGIN_URL . 'assets/pltm.css', array(), PLTM_VERSION);
        wp_enqueue_script('pltm-front-js', PLTM_PLUGIN_URL . 'assets/pltm.js', array(), PLTM_VERSION, true);
    }

    public function admin_page() {
        if (!current_user_can('manage_options')) return;
        $this->maybe_create_table();
        global $wpdb;
        $items = $wpdb->get_results("SELECT id,title,layout,created_at FROM {$this->table} ORDER BY id DESC");
        $edit_id = isset($_GET['view']) ? absint($_GET['view']) : 0;
        $view = $edit_id ? $this->get_table($edit_id) : null;
        ?>
        <div class="wrap pltm-wrap">
            <h1>Price List Table Manager</h1>
            <?php if(isset($_GET['pltm_msg'])): ?><div class="notice notice-success"><p><?php echo esc_html($_GET['pltm_msg']); ?></p></div><?php endif; ?>
            <div class="pltm-card">
                <h2>Upload Excel/CSV</h2>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field('pltm_upload'); ?>
                    <input type="hidden" name="action" value="pltm_upload">
                    <p><label>Table Title<br><input type="text" name="title" class="regular-text" required placeholder="Example: Main Price List"></label></p>
                    <p><label>Layout<br><select name="layout"><option value="modern">Modern Blue</option><option value="clean">Clean White</option><option value="darkhead">Dark Header</option></select></label></p>
                    <p><input type="file" name="price_file" accept=".csv,.xlsx" required></p>
                    <p><button class="button button-primary">Upload & Create Shortcode</button></p>
                    <p class="description">Supports CSV and simple XLSX files. First row is used as column headers.</p>
                </form>
            </div>

            <div class="pltm-card">
                <h2>Saved Tables</h2>
                <table class="widefat striped">
                    <thead><tr><th>ID</th><th>Title</th><th>Shortcode</th><th>Layout</th><th>Created</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php if($items): foreach($items as $it): ?>
                        <tr>
                            <td><?php echo esc_html($it->id); ?></td>
                            <td><?php echo esc_html($it->title); ?></td>
                            <td><code>[price_list_table id="<?php echo esc_attr($it->id); ?>"]</code></td>
                            <td><?php echo esc_html($it->layout); ?></td>
                            <td><?php echo esc_html($it->created_at); ?></td>
                            <td>
                                <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=pltm&view=' . $it->id)); ?>">Preview</a>
                                <a class="button button-link-delete" onclick="return confirm('Delete this table?')" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=pltm_delete&id=' . $it->id), 'pltm_delete_' . $it->id)); ?>">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="6">No tables yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if($view): ?>
                <div class="pltm-card"><h2>Preview: <?php echo esc_html($view->title); ?></h2><?php echo $this->render_table($view, true); ?></div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function handle_upload() {
        if (!current_user_can('manage_options') || !check_admin_referer('pltm_upload')) wp_die('Permission denied');
        if (empty($_FILES['price_file']['tmp_name'])) wp_die('No file uploaded');
        $title = sanitize_text_field($_POST['title']);
        $layout = sanitize_key($_POST['layout']);
        if (!in_array($layout, array('modern','clean','darkhead'), true)) $layout = 'modern';
        $name = sanitize_file_name($_FILES['price_file']['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, array('csv','xlsx'), true)) wp_die('Only CSV and XLSX files are allowed.');
        $data = ($ext === 'csv') ? $this->parse_csv($_FILES['price_file']['tmp_name']) : $this->parse_xlsx($_FILES['price_file']['tmp_name']);
        if (empty($data) || count($data) < 1) wp_die('Could not read file or file is empty.');
        $headers = array_map('sanitize_text_field', array_shift($data));
        $rows = array();
        foreach ($data as $r) {
            $row = array();
            for ($i=0; $i<count($headers); $i++) $row[] = isset($r[$i]) ? wp_kses_post((string)$r[$i]) : '';
            if (implode('', $row) !== '') $rows[] = $row;
        }
        global $wpdb;
        $this->maybe_create_table();
        $wpdb->insert($this->table, array(
            'title'=>$title,
            'headers'=>wp_json_encode($headers),
            'row_data'=>wp_json_encode($rows),
            'layout'=>$layout,
            'created_at'=>current_time('mysql')
        ), array('%s','%s','%s','%s','%s'));
        if (!$wpdb->insert_id) {
            wp_die('Table was not saved. Database error: ' . esc_html($wpdb->last_error));
        }
        wp_safe_redirect(admin_url('admin.php?page=pltm&pltm_msg=' . rawurlencode('Table created successfully. Shortcode is ready.')));
        exit;
    }

    public function handle_delete() {
        if (!current_user_can('manage_options')) wp_die('Permission denied');
        $id = absint($_GET['id']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'pltm_delete_' . $id)) wp_die('Invalid nonce');
        global $wpdb; $wpdb->delete($this->table, array('id'=>$id), array('%d'));
        wp_safe_redirect(admin_url('admin.php?page=pltm&pltm_msg=' . rawurlencode('Table deleted.'))); exit;
    }

    private function parse_csv($file) {
        $rows = array();
        if (($h = fopen($file, 'r')) !== false) {
            while (($data = fgetcsv($h, 0, ',')) !== false) $rows[] = $data;
            fclose($h);
        }
        return $rows;
    }

    private function parse_xlsx($file) {
        if (!class_exists('ZipArchive')) wp_die('Server ZipArchive extension is required for XLSX upload. CSV upload will still work.');
        $zip = new ZipArchive();
        if ($zip->open($file) !== true) return array();
        $shared = array();
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx && isset($sx->si)) foreach ($sx->si as $si) $shared[] = $this->xlsx_text($si);
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) { $zip->close(); return array(); }
        $sx = simplexml_load_string($sheetXml);
        $rows = array();
        if ($sx && isset($sx->sheetData->row)) {
            foreach ($sx->sheetData->row as $rowNode) {
                $row = array();
                $lastIndex = 0;
                foreach ($rowNode->c as $c) {
                    $ref = (string)$c['r'];
                    $colLetters = preg_replace('/[0-9]/', '', $ref);
                    $idx = $this->col_to_index($colLetters);
                    while ($lastIndex < $idx) { $row[] = ''; $lastIndex++; }
                    $type = (string)$c['t'];
                    $val = isset($c->v) ? (string)$c->v : '';
                    if ($type === 's') $val = isset($shared[(int)$val]) ? $shared[(int)$val] : '';
                    elseif ($type === 'inlineStr' && isset($c->is)) $val = $this->xlsx_text($c->is);
                    $row[] = $val; $lastIndex++;
                }
                $rows[] = $row;
            }
        }
        $zip->close(); return $rows;
    }

    private function xlsx_text($node) {
        $text = '';
        if (isset($node->t)) $text .= (string)$node->t;
        if (isset($node->r)) foreach ($node->r as $r) if (isset($r->t)) $text .= (string)$r->t;
        return $text;
    }

    private function col_to_index($letters) {
        $n = 0; $letters = strtoupper($letters);
        for ($i=0; $i<strlen($letters); $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
        return max(0, $n - 1);
    }

    private function get_table($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id=%d", $id));
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('id'=>0), $atts);
        $table = $this->get_table(absint($atts['id']));
        if (!$table) return '<p>Price table not found.</p>';
        return $this->render_table($table, false);
    }

    private function render_table($table, $admin=false) {
        $headers = json_decode($table->headers, true); $rows = json_decode($table->row_data, true);
        if (!is_array($headers) || !is_array($rows)) return '<p>Invalid table data.</p>';
        $uid = 'pltm_' . intval($table->id) . '_' . wp_rand(1000,9999);
        $pdf = wp_nonce_url(admin_url('admin-post.php?action=pltm_export_pdf&id=' . intval($table->id)), 'pltm_export_' . intval($table->id));
        $csv = wp_nonce_url(admin_url('admin-post.php?action=pltm_export_csv&id=' . intval($table->id)), 'pltm_export_' . intval($table->id));
        ob_start(); ?>
        <div class="pltm-table-wrap pltm-layout-<?php echo esc_attr($table->layout); ?>" id="<?php echo esc_attr($uid); ?>" data-default="50">
            <div class="pltm-toolbar">
                <input class="pltm-search" type="search" placeholder="Search...">
                <select class="pltm-per-page"><option value="50">50 rows</option><option value="100">100 rows</option><option value="all">All</option></select>
                <a class="pltm-btn" href="<?php echo esc_url($csv); ?>">Download CSV</a>
                <a class="pltm-btn" href="<?php echo esc_url($pdf); ?>">Download PDF</a>
            </div>
            <div class="pltm-scroll"><table class="pltm-table"><thead><tr><?php foreach($headers as $h): ?><th><?php echo esc_html($h); ?></th><?php endforeach; ?></tr></thead><tbody>
            <?php foreach($rows as $r): ?><tr><?php foreach($headers as $i=>$h): ?><td><?php echo esc_html(isset($r[$i])?$r[$i]:''); ?></td><?php endforeach; ?></tr><?php endforeach; ?>
            </tbody></table></div><div class="pltm-pagination"></div>
        </div>
        <?php return ob_get_clean();
    }

    public function export_csv() {
        $id = absint($_GET['id']); if (!wp_verify_nonce($_GET['_wpnonce'], 'pltm_export_' . $id)) wp_die('Invalid download link');
        $table = $this->get_table($id); if (!$table) wp_die('Table not found');
        $headers = json_decode($table->headers, true); $rows = json_decode($table->row_data, true);
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="price-table-' . $id . '.csv"');
        $out = fopen('php://output', 'w'); fputcsv($out, $headers); foreach($rows as $r) fputcsv($out, $r); fclose($out); exit;
    }

    public function export_pdf() {
        $id = absint($_GET['id']); if (!wp_verify_nonce($_GET['_wpnonce'], 'pltm_export_' . $id)) wp_die('Invalid download link');
        $table = $this->get_table($id); if (!$table) wp_die('Table not found');
        $headers = json_decode($table->headers, true); $rows = json_decode($table->row_data, true);
        $domain = parse_url(home_url(), PHP_URL_HOST);
        $content = $table->title . "\nWatermark: " . $domain . "\n\n" . implode(' | ', $headers) . "\n";
        foreach($rows as $r) $content .= implode(' | ', array_map('wp_strip_all_tags', $r)) . "\n";
        $pdf = $this->simple_pdf($content, $domain);
        header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="price-table-' . $id . '.pdf"'); header('Content-Length: '.strlen($pdf)); echo $pdf; exit;
    }

    private function simple_pdf($text, $watermark) {
        $lines = preg_split('/\r\n|\r|\n/', $text); $pages = array(); $page = array();
        foreach($lines as $line) { $chunks = str_split($line, 105); if (!$chunks) $chunks=array(''); foreach($chunks as $ch){ $page[]=$ch; if(count($page)>=45){$pages[]=$page;$page=array();}} }
        if ($page) $pages[] = $page; if (!$pages) $pages = array(array(''));
        $objects = array(); $pagesKids = array();
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '';
        $fontObj = 3; $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $next = 4;
        foreach($pages as $p) {
            $content = "q 0.90 0.90 0.90 rg /F1 48 Tf 130 420 Td (".$this->pdf_escape($watermark).") Tj Q\n";
            $content .= "BT /F1 10 Tf 40 790 Td 12 TL ";
            foreach($p as $ln) $content .= '(' . $this->pdf_escape(substr($ln,0,180)) . ") Tj T* ";
            $content .= "ET";
            $contentObj = $next++; $pageObj = $next++;
            $objects[$contentObj-1] = '<< /Length '.strlen($content).' >>' . "\nstream\n" . $content . "\nendstream";
            $objects[$pageObj-1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontObj.' 0 R >> >> /Contents '.$contentObj.' 0 R >>';
            $pagesKids[] = $pageObj . ' 0 R';
        }
        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $pagesKids).'] /Count '.count($pagesKids).' >>';
        $pdf = "%PDF-1.4\n"; $offsets = array(0);
        foreach($objects as $i=>$obj){ $offsets[$i+1]=strlen($pdf); $pdf .= ($i+1)." 0 obj\n".$obj."\nendobj\n"; }
        $xref = strlen($pdf); $pdf .= "xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= "trailer\n<< /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
    private function pdf_escape($s) { return str_replace(array('\\','(',')'), array('\\\\','\\(','\\)'), $s); }
}
PLTM_Plugin::instance();
