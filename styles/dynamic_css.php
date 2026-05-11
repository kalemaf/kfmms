<?php 

function css_site($style_sheet) {

    //determine font for this platform
    if (browser_is_windows() && browser_is_ie()) {

        //ie needs smaller fonts than anyone else
        $font_size='x-small';
        $font_smaller='7pt';
        $font_smallest='7pt';


    } else if (browser_is_windows()) {

        //netscape or "other" on wintel
        $font_size='small';
               $font_smaller='x-small';
        $font_smallest='x-small';

    } else if (browser_is_mac()){

        //mac users need bigger fonts
        $font_size='medium';
        $font_smaller='small';
        $font_smallest='x-small';

    } else {

        //linux and other users
        $font_size='small';
        $font_smaller='x-small';
        $font_smallest='x-small';

    }

    $site_fonts='verdana, arial, helvetica, sans-serif';

    // Read and output CSS file content
    if (file_exists($style_sheet)) {
        echo file_get_contents($style_sheet);
    } else {
        echo "/* CSS file not found: $style_sheet */";
    }

}

?>