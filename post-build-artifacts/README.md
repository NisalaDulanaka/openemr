Houses necessary composer post-build changes

 - jwt stuff is needed until PHP 7.3 EOL.
   - When no longer needed, need to remove corresponding code from gulpfile.js.
   - Also, whenever update composer packages, need to ensure jwt package remains
     at 3.4.1 . If it increments, then need to analyze it to ensure the modified
     scripts remains relevant.
