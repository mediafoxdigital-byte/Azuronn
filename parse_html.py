from bs4 import BeautifulSoup
import json
with open("output3.html") as f:
    soup = BeautifulSoup(f.read(), "html.parser")

print("Metal Inputs:")
for inp in soup.select('input[name="metal"]'):
    print(inp)

print("\nShape Inputs:")
for inp in soup.select('input[name="shape"], input[name="diamond_shape"]'):
    print(inp.parent)

print("\nBand Inputs:")
for inp in soup.select('input[name="band_claw_metal"]'):
    print(inp)
