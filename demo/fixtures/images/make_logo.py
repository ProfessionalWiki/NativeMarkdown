"""Square markdown-mark wiki icon: blue rounded square, white M + down arrow."""
import os
from PIL import Image, ImageDraw, ImageFont

S = 200
img = Image.new("RGBA", (S, S), (0, 0, 0, 0))
d = ImageDraw.Draw(img)

d.rounded_rectangle([4, 4, S - 4, S - 4], radius=28, fill=(50, 115, 155, 255))  # Professional Wiki brand blue #32739b

f = ImageFont.truetype("/usr/share/fonts/open-sans/OpenSans-Bold.ttf", 108)
mw = d.textlength("M", font=f)
d.text((S * 0.30 - mw / 2, S / 2 - 76), "M", font=f, fill="white")

# Down arrow: shaft + triangle head, right of the M
cx = int(S * 0.70)
top, bottom = 62, 138
shaft_w = 13
d.rectangle([cx - shaft_w // 2, top, cx + shaft_w // 2, bottom - 26], fill="white")
d.polygon([(cx - 26, bottom - 30), (cx + 26, bottom - 30), (cx, bottom)], fill="white")

img.save(os.path.join(os.path.dirname(os.path.abspath(__file__)), "markdown-wiki-icon.png"))
print("done")
