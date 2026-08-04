import sys

with open("/home/hamid/azuronn.com/assets/css/style.css", "r") as f:
    lines = f.readlines()

# Extract lines (0-indexed)
# 1971 to 2186 => 1970 to 2186
block1 = "".join(lines[1970:2186])

# 4238:4247 => 4237 to 4247
block2 = "@media (min-width: 1024px) {\n" + "".join(lines[4237:4247]) + "}\n"

# 4749:4803 => 4748 to 4803
block3 = "@media (max-width: 768px) {\n" + "".join(lines[4748:4803]) + "}\n"

out = f"\n/* --- DIAMOND SHAPE MODULE --- */\n{block1}\n{block2}\n{block3}\n"

with open("/home/hamid/Downloads/azuronn_2/assets/css/style.css", "a") as f:
    f.write(out)

