import sys

with open("/home/hamid/azuronn.com/assets/css/style.css", "r") as f:
    lines = f.readlines()

block1 = "".join(lines[2180:2341])
block1 = block1.replace('rgba(26, 43, 35, 0.94)', '#024530')

extra = """
/* Diamond Shape Missing Styles */
.sec-hdr-diamond {
  max-width: 760px;
  margin-bottom: 28px;
  text-align: left;
}
.sec-hdr-diamond h2 {
  font-family: var(--serif);
  font-weight: 600;
  letter-spacing: -0.015em;
  line-height: 0.95;
  color: var(--gold-dark, #b8924f);
  font-size: clamp(2.4rem, 4vw, 4.2rem);
  margin: 10px 0 0;
  text-shadow: 0 4px 12px rgba(178, 157, 109, 0.12);
}
.sec-hdr-diamond .eyebrow {
  display: inline-flex;
  font-size: 0.78rem;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--gold-dark, #b8924f);
}

.btn-shop-diamond {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-height: 48px;
  padding: 0 20px;
  border-radius: 999px;
  transition: transform 220ms ease, background 220ms ease, color 220ms ease, box-shadow 220ms ease;
  background: linear-gradient(135deg, #024530 0%, #036648 68%, #b8924f 140%);
  color: #fff;
  box-shadow: 0 16px 36px rgba(2, 69, 48, 0.22);
  text-decoration: none;
  font-size: 14px;
}
.btn-shop-diamond:hover {
  background: linear-gradient(135deg, #013324 0%, #024530 68%, #c9a96e 138%);
  transform: translateY(-3px);
  box-shadow: 0 16px 34px rgba(2, 69, 48, 0.18);
  color: #fff;
}
"""

with open("/home/hamid/Downloads/azuronn_2/assets/css/style.css", "a") as f:
    f.write(extra + "\n" + block1)
