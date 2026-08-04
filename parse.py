import sys

# Extract
with open("/home/hamid/azuronn.com/pages/home.php", "r") as f:
    lines = f.readlines()

array_str = "".join(lines[91:203]) # lines 92-203
html_str = "".join(lines[386:431]) # lines 387-431

out = f"<?php\n{array_str}?>\n\n{html_str}\n"

with open("/home/hamid/Downloads/azuronn_2/includes/partials/diamond-shapes.php", "w") as f:
    f.write(out)

