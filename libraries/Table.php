<?php
class HTML_Table {
    private $rows = [];
    private $rowAttributes = [];
    private $colAttributes = [];

    private $tableProp = [];

    public function __construct($tableProp = []) {
        $this->tableProp = $tableProp;
    }

    public function addRow($row = [], $attr = [], $type = 'TD', $escape = true) {
        $safeRow = [];

        // Loop through row and ensure each cell exists
        foreach ($row as $i => $cell) {
            $safeRow[$i] = isset($cell) ? $cell : '';
        }

        $this->rows[] = $safeRow;
        $this->rowAttributes[] = $attr;
        return count($this->rows) - 1;
    }

    public function setRowAttributes($rowIndex, $attr = [], $merge = true) {
        if (!isset($this->rowAttributes[$rowIndex])) {
            $this->rowAttributes[$rowIndex] = [];
        }

        if ($merge) {
            $this->rowAttributes[$rowIndex] = array_merge($this->rowAttributes[$rowIndex], $attr);
        } else {
            $this->rowAttributes[$rowIndex] = $attr;
        }
    }

    public function updateColAttributes($colIndex, $attr = []) {
        if (!isset($this->colAttributes[$colIndex])) {
            $this->colAttributes[$colIndex] = [];
        }

        $this->colAttributes[$colIndex] = array_merge($this->colAttributes[$colIndex], $attr);
    }

    public function getColCount() {
        $max = 0;
        foreach ($this->rows as $row) {
            $count = count($row);
            if ($count > $max) $max = $count;
        }
        return $max;
    }

    public function toHtml() {
        $html = '<table';
        foreach ($this->tableProp as $key => $val) {
            $html .= " $key=\"$val\"";
        }
        $html .= '>';

        foreach ($this->rows as $rIndex => $row) {
            $attrStr = '';
            if (isset($this->rowAttributes[$rIndex])) {
                foreach ($this->rowAttributes[$rIndex] as $k => $v) {
                    $attrStr .= " $k=\"$v\"";
                }
            }

            $html .= "<tr$attrStr>";

            foreach ($row as $cIndex => $cell) {
                $cellAttrStr = '';
                if (isset($this->colAttributes[$cIndex])) {
                    foreach ($this->colAttributes[$cIndex] as $k => $v) {
                        $cellAttrStr .= " $k=\"$v\"";
                    }
                }

                $html .= "<td$cellAttrStr>" . ($cell ?? '') . "</td>";
            }

            $html .= '</tr>';
        }

        $html .= '</table>';
        return $html;
    }
}
?>
