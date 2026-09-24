#!/bin/bash
# Double-click this file on a Mac to preview the website at http://localhost:8080/
cd "$(dirname "$0")"
echo ""
echo "  Website preview is running at  http://localhost:8080/"
echo "  Your browser should open by itself. Refresh the page to see changes you make."
echo "  Close this window when you are done."
echo ""
(sleep 1; open "http://localhost:8080/") &
python3 -m http.server 8080 --bind 127.0.0.1
