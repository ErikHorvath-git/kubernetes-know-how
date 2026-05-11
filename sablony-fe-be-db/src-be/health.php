<?php
// liveness — len že PHP/Apache beží. NEPING-uje DB (DB výpadok ≠ smrť BE)
header('Content-Type: text/plain');
echo "OK\n";
