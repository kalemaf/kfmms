<?php
header("Content-Type: text/css");
$font_smaller = $font_smaller ?? "12px";
?>

body {
    font-family: arial, helvetica, geneva, sans-serif;
    font-size: <?php echo $font_smaller; ?>;
    background: #FFFFFF;
    color: #333333;
}

a {
    color: #000066;
}

form {
    margin-bottom: 0;
    margin-top: 0;
    color: #000066;
}

textarea, input, select {
    background-color: #FAFAFA;
    border: 1px solid #8CACBB;
    padding: 2px;
    margin: 1px;
    font-family: arial, sans-serif;
    font-size: 12px;
    color: #333333;
}

input[type=image] {
    border: none;
    background: transparent;
    font-size: <?php echo $font_smaller; ?>;
}

input.save {
    position: fixed;
    top: 0;
    right: 0;
}

/* BOX STYLES */

div.box h5 {
    border: 1px solid #8CACBB;
    border-bottom: none;
    background: #5961a0;
    color: #DEDEDE;
    padding: 0 1em;
    text-transform: lowercase;
    display: inline-block;
    font-size: x-small;
    font-weight: bold;
}

div.box {
    margin: 0 0 1.5em 0;
    padding: 0 5px;
}

div.box div.body {
    border: 1px solid #8CACBB;
    background: #EEEEEE;
}

div.box .content {
    padding: 0.5em;
}

div.box a {
    text-decoration: none;
}

div.box a:hover {
    color: #0000FF;
    text-decoration: underline;
}

.no_link {
    color: #777788;
}

div.box .even {
    background-color: #F7F9FA;
}

div.box .odd {
    background-color: transparent;
}

div.spacer {
    margin: 1em;
}

.currentNavItem {
    color: black;
    font-weight: bold;
}
