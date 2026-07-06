"""Generate two clean wiki-style diagrams for the dogfood pages."""
from PIL import Image, ImageDraw, ImageFont

REG = "/usr/share/fonts/open-sans/OpenSans-Regular.ttf"
SEMI = "/usr/share/fonts/open-sans/OpenSans-Semibold.ttf"

INK = (40, 48, 61)
MUTED = (108, 117, 130)
BORDER = (178, 186, 197)
ARROW = (108, 117, 130)

FILLS = {
    "blue": (222, 235, 250),
    "green": (223, 240, 226),
    "amber": (250, 238, 217),
    "gray": (237, 239, 242),
}
EDGES = {
    "blue": (128, 165, 208),
    "green": (134, 183, 143),
    "amber": (211, 178, 118),
    "gray": (183, 189, 197),
}


def font(path, size):
    return ImageFont.truetype(path, size)


def box(d, x, y, w, h, title, sub, tone, title_size=26, sub_size=19):
    d.rounded_rectangle([x, y, x + w, y + h], radius=10,
                        fill=FILLS[tone], outline=EDGES[tone], width=2)
    tf = font(SEMI, title_size)
    sf = font(REG, sub_size)
    tw = d.textlength(title, font=tf)
    d.text((x + (w - tw) / 2, y + h / 2 - (title_size + sub_size + 8) / 2),
           title, font=tf, fill=INK)
    sw = d.textlength(sub, font=sf)
    d.text((x + (w - sw) / 2, y + h / 2 + 2), sub, font=sf, fill=MUTED)


def arrow(d, x1, y1, x2, y2, label=None):
    import math
    d.line([x1, y1, x2, y2], fill=ARROW, width=3)
    ang = math.atan2(y2 - y1, x2 - x1)
    tip = (x2, y2)
    left = (x2 - 15 * math.cos(ang) + 7 * math.sin(ang), y2 - 15 * math.sin(ang) - 7 * math.cos(ang))
    right = (x2 - 15 * math.cos(ang) - 7 * math.sin(ang), y2 - 15 * math.sin(ang) + 7 * math.cos(ang))
    d.polygon([tip, left, right], fill=ARROW)
    if label:
        lf = font(REG, 18)
        lw = d.textlength(label, font=lf)
        d.text(((x1 + x2) / 2 - lw / 2, y1 - 32), label, font=lf, fill=MUTED)


def pipeline():
    W, H = 1240, 320
    img = Image.new("RGB", (W, H), "white")
    d = ImageDraw.Draw(img)

    tf = font(SEMI, 30)
    d.text((40, 28), "Deployment pipeline", font=tf, fill=INK)

    y, bw, bh = 110, 240, 120
    xs = [40, 340, 640, 940]
    box(d, xs[0], y, bw, bh, "Pull request", "review + merge", "gray")
    box(d, xs[1], y, bw, bh, "CI build", "tests, image push", "blue")
    box(d, xs[2], y, bw, bh, "Staging", "smoke tests", "amber")
    box(d, xs[3], y, bw, bh, "Production", "gradual rollout", "green")

    mid = y + bh / 2
    arrow(d, xs[0] + bw + 6, mid, xs[1] - 8, mid)
    arrow(d, xs[1] + bw + 6, mid, xs[2] - 8, mid)
    arrow(d, xs[2] + bw + 6, mid, xs[3] - 8, mid)

    lf = font(REG, 18)
    for text, x_from, x_to in [("merge", xs[0] + bw, xs[1]), ("deploy.sh", xs[1] + bw, xs[2]),
                               ("promote", xs[2] + bw, xs[3])]:
        lw = d.textlength(text, font=lf)
        d.text(((x_from + x_to) / 2 - lw / 2, 78), text, font=lf, fill=MUTED)

    nf = font(REG, 18)
    d.text((40, 262), "Rollback: redeploy the previous image tag with deploy.sh production --tag <previous>",
           font=nf, fill=MUTED)
    img.save("/tmp/claude-1004/-var-home-ccc-workspace-mediawiki-dev/a1120d5a-3989-427c-9e23-6dede5655b0e/scratchpad/Deployment_Pipeline.png")


def architecture():
    W, H = 1140, 640
    img = Image.new("RGB", (W, H), "white")
    d = ImageDraw.Draw(img)

    tf = font(SEMI, 30)
    d.text((40, 28), "Wiki platform architecture", font=tf, fill=INK)

    bw, bh = 260, 110

    # Top row: clients
    box(d, 140, 100, bw, bh, "Browsers", "readers + editors", "gray")
    box(d, 740, 100, bw, bh, "AI agents", "MCP / REST clients", "gray")

    # Middle: app server
    box(d, 440, 280, bw, bh, "MediaWiki", "NativeMarkdown", "blue")

    # Bottom row: backing services
    box(d, 140, 470, bw, bh, "MariaDB", "page storage", "amber")
    box(d, 740, 470, bw, bh, "Elasticsearch", "full-text search", "green")

    # Arrows into MediaWiki
    arrow(d, 270, 100 + bh + 6, 530, 272, None)
    arrow(d, 870, 100 + bh + 6, 610, 272, None)
    # MediaWiki to services
    arrow(d, 530, 280 + bh + 6, 290, 462, None)
    arrow(d, 610, 280 + bh + 6, 850, 462, None)

    lf = font(REG, 18)
    d.text((330, 205), "HTML pages", font=lf, fill=MUTED)
    d.text((680, 205), "raw Markdown", font=lf, fill=MUTED)
    d.text((310, 425), "revisions", font=lf, fill=MUTED)
    d.text((690, 425), "search index", font=lf, fill=MUTED)

    img.save("/tmp/claude-1004/-var-home-ccc-workspace-mediawiki-dev/a1120d5a-3989-427c-9e23-6dede5655b0e/scratchpad/Architecture_Overview.png")


pipeline()
architecture()
print("done")
