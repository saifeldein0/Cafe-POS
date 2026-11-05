"""Builds docs/CafePOS-Guide.pdf (bilingual FR + EN owner/cashier guide) with ReportLab.

Usage (from the repo root):
    uv run --no-project --with reportlab --with pillow python docs/guide-src/build.py

Screenshots come from docs/guide-src/img (made by shoot.mjs + crop.py).
"""
from pathlib import Path

from PIL import Image as PILImage
from reportlab.lib.colors import HexColor, white
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import cm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import (
    BaseDocTemplate, CondPageBreak, Frame, Image, KeepTogether, NextPageTemplate,
    PageBreak, PageTemplate, Paragraph, Spacer, Table, TableStyle,
)

HERE = Path(__file__).parent
IMG = HERE / "img"
OUT = HERE.parent / "CafePOS-Guide.pdf"

COPPER = HexColor("#8f4a1d")
COPPER_LIGHT = HexColor("#f6ece3")
STONE = HexColor("#f3f1ed")
INK = HexColor("#2b2118")
MUTED = HexColor("#6b5d52")
GREEN = HexColor("#1f6b45")
LINE = HexColor("#ddd5cc")

FONTS = Path("C:/Windows/Fonts")
pdfmetrics.registerFont(TTFont("Body", str(FONTS / "segoeui.ttf")))
pdfmetrics.registerFont(TTFont("Body-Bold", str(FONTS / "segoeuib.ttf")))
pdfmetrics.registerFont(TTFont("Body-Italic", str(FONTS / "segoeuii.ttf")))
pdfmetrics.registerFont(TTFont("Body-Semi", str(FONTS / "seguisb.ttf")))
pdfmetrics.registerFont(TTFont("Head", str(FONTS / "georgiab.ttf")))
pdfmetrics.registerFontFamily("Body", normal="Body", bold="Body-Bold", italic="Body-Italic", boldItalic="Body-Bold")

PAGE_W, PAGE_H = A4
MARGIN = 2.2 * cm
CONTENT_W = PAGE_W - 2 * MARGIN

S = {
    "body": ParagraphStyle("body", fontName="Body", fontSize=12, leading=17, textColor=INK, spaceAfter=6),
    "intro": ParagraphStyle("intro", fontName="Body", fontSize=12.5, leading=18, textColor=MUTED, spaceAfter=10),
    "h1": ParagraphStyle("h1", fontName="Head", fontSize=21, leading=26, textColor=COPPER, spaceBefore=4, spaceAfter=4),
    "step": ParagraphStyle("step", fontName="Body", fontSize=12, leading=17, textColor=INK),
    "num": ParagraphStyle("num", fontName="Body-Bold", fontSize=12, leading=17, textColor=white, alignment=TA_CENTER),
    "tip": ParagraphStyle("tip", fontName="Body", fontSize=11.5, leading=16.5, textColor=INK),
    "caption": ParagraphStyle("caption", fontName="Body-Italic", fontSize=10, leading=13, textColor=MUTED, alignment=TA_CENTER, spaceBefore=3, spaceAfter=10),
    "toc": ParagraphStyle("toc", fontName="Body", fontSize=12.5, leading=20, textColor=INK),
}


# ---------- building blocks ----------

def steps(items):
    def badge(i):
        t_ = Table([[Paragraph(str(i), S["num"])]], colWidths=[0.72 * cm], rowHeights=[0.66 * cm])
        t_.setStyle(TableStyle([
            ("BACKGROUND", (0, 0), (-1, -1), COPPER), ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("LEFTPADDING", (0, 0), (-1, -1), 0), ("RIGHTPADDING", (0, 0), (-1, -1), 0),
            ("TOPPADDING", (0, 0), (-1, -1), 0), ("BOTTOMPADDING", (0, 0), (-1, -1), 2),
            ("ROUNDEDCORNERS", [5, 5, 5, 5]),
        ]))
        return t_
    rows = [[badge(i), Paragraph(t, S["step"])] for i, t in enumerate(items, 1)]
    t = Table(rows, colWidths=[0.8 * cm, CONTENT_W - 1.2 * cm], hAlign="LEFT")
    style = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("RIGHTPADDING", (0, 0), (0, -1), 0),
        ("LEFTPADDING", (1, 0), (1, -1), 10),
        ("TOPPADDING", (0, 0), (-1, -1), 3),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
    ]
    t.setStyle(TableStyle(style))
    return [t, Spacer(1, 8)]


def tip(text, color=COPPER_LIGHT, bar=COPPER):
    t = Table([[Paragraph(text, S["tip"])]], colWidths=[CONTENT_W])
    t.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), color),
        ("LINEBEFORE", (0, 0), (0, -1), 4, bar),
        ("LEFTPADDING", (0, 0), (-1, -1), 12),
        ("RIGHTPADDING", (0, 0), (-1, -1), 12),
        ("TOPPADDING", (0, 0), (-1, -1), 8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 9),
    ]))
    return [t, Spacer(1, 10)]


def shot(name, lang, caption=None, width=CONTENT_W):
    path = IMG / f"{lang}-{name}.jpg"
    w, h = PILImage.open(path).size
    width = min(width, CONTENT_W)
    height = width * h / w
    img = Image(str(path), width=width, height=height)
    frame = Table([[img]], colWidths=[width + 2])
    frame.setStyle(TableStyle([
        ("BOX", (0, 0), (-1, -1), 0.8, LINE),
        ("LEFTPADDING", (0, 0), (-1, -1), 1), ("RIGHTPADDING", (0, 0), (-1, -1), 1),
        ("TOPPADDING", (0, 0), (-1, -1), 1), ("BOTTOMPADDING", (0, 0), (-1, -1), 1),
    ]))
    parts = [Spacer(1, 4), frame]
    parts.append(Paragraph(caption, S["caption"]) if caption else Spacer(1, 10))
    return [KeepTogether(parts)]


def heading(n, title, intro=None):
    badge = Table([[Paragraph(str(n), ParagraphStyle("b", parent=S["num"], fontSize=15, leading=20))]],
                  colWidths=[0.95 * cm], rowHeights=[0.95 * cm])
    badge.setStyle(TableStyle([
        ("BACKGROUND", (0, 0), (-1, -1), COPPER), ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("ROUNDEDCORNERS", [14, 14, 14, 14]),
    ]))
    row = Table([[badge, Paragraph(title, S["h1"])]], colWidths=[1.3 * cm, CONTENT_W - 1.3 * cm])
    row.setStyle(TableStyle([
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"), ("LEFTPADDING", (0, 0), (-1, -1), 0),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 2),
    ]))
    rule = Table([[""]], colWidths=[CONTENT_W], rowHeights=[2])
    rule.setStyle(TableStyle([("LINEBELOW", (0, 0), (-1, -1), 1.2, COPPER)]))
    out = [CondPageBreak(9 * cm), row, rule, Spacer(1, 8)]
    if intro:
        out.append(Paragraph(intro, S["intro"]))
    return out


def p(text):
    return Paragraph(text, S["body"])


def b(text):
    """Bold UI label."""
    return f"<b>{text}</b>"


# ---------- page decorations ----------

def draw_cup(c, cx, cy, scale=1.0, color=white):
    """Simple cafe cup with steam, centred on (cx, cy)."""
    c.saveState()
    c.translate(cx, cy)
    c.scale(scale, scale)
    c.setFillColor(color)
    c.setStrokeColor(color)
    # cup body
    p_ = c.beginPath()
    p_.moveTo(-38, 18)
    p_.lineTo(38, 18)
    p_.curveTo(38, -18, 22, -34, 0, -34)
    p_.curveTo(-22, -34, -38, -18, -38, 18)
    p_.close()
    c.drawPath(p_, fill=1, stroke=0)
    # handle
    c.setLineWidth(7)
    c.circle(44, 2, 12, stroke=1, fill=0)
    # saucer
    c.roundRect(-52, -46, 104, 8, 4, fill=1, stroke=0)
    # steam
    c.setLineWidth(4.5)
    c.setLineCap(1)
    for x in (-16, 0, 16):
        s = c.beginPath()
        s.moveTo(x, 28)
        s.curveTo(x - 9, 38, x + 9, 46, x, 58)
        c.drawPath(s, stroke=1, fill=0)
    c.restoreState()


def on_cover(c, doc):
    c.saveState()
    c.setFillColor(STONE)
    c.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    # copper band at the bottom
    c.setFillColor(COPPER)
    c.rect(0, 0, PAGE_W, 4.2 * cm, fill=1, stroke=0)
    # logo disc
    cx, cy = PAGE_W / 2, PAGE_H - 9.5 * cm
    c.setFillColor(COPPER)
    c.circle(cx, cy, 3.6 * cm, fill=1, stroke=0)
    draw_cup(c, cx, cy - 0.2 * cm, scale=1.25)
    # title
    c.setFillColor(INK)
    c.setFont("Head", 46)
    c.drawCentredString(cx, cy - 6.3 * cm, "CafePOS")
    c.setFillColor(COPPER)
    c.setFont("Body-Semi", 18)
    c.drawCentredString(cx, cy - 7.6 * cm, "Guide de la caisse  ·  Till guide")
    c.setFillColor(MUTED)
    c.setFont("Body", 13)
    c.drawCentredString(cx, cy - 8.6 * cm, "Pour le gérant et le caissier  ·  For the owner and the cashier")
    # contents
    c.setFillColor(INK)
    c.setFont("Body-Bold", 14)
    y = cy - 11.2 * cm
    c.drawCentredString(cx, y, f"Partie 1 — Guide en français  ·  page {doc._gg_fr_page}")
    c.drawCentredString(cx, y - 0.9 * cm, f"Part 2 — Guide in English  ·  page {doc._gg_en_page}")
    c.setFillColor(white)
    c.setFont("Body", 12)
    c.drawCentredString(cx, 2.4 * cm, "Gardez ce guide près de la caisse.  ·  Keep this guide next to the till.")
    c.restoreState()


def on_divider(c, doc):
    c.saveState()
    c.setFillColor(COPPER)
    c.rect(0, 0, PAGE_W, PAGE_H, fill=1, stroke=0)
    draw_cup(c, PAGE_W / 2, PAGE_H / 2 + 5 * cm, scale=1.5)
    title, sub = doc._gg_divider
    c.setFillColor(white)
    c.setFont("Head", 34)
    c.drawCentredString(PAGE_W / 2, PAGE_H / 2 - 0.5 * cm, title)
    c.setFont("Body", 15)
    c.drawCentredString(PAGE_W / 2, PAGE_H / 2 - 1.8 * cm, sub)
    c.restoreState()


def on_page(c, doc):
    c.saveState()
    c.setStrokeColor(LINE)
    c.setLineWidth(0.7)
    c.line(MARGIN, 1.6 * cm, PAGE_W - MARGIN, 1.6 * cm)
    c.setFont("Body", 10)
    c.setFillColor(MUTED)
    c.drawString(MARGIN, 1.05 * cm, doc._gg_footer)
    c.setFillColor(COPPER)
    c.setFont("Body-Bold", 11)
    c.drawRightString(PAGE_W - MARGIN, 1.05 * cm, str(c.getPageNumber()))
    c.restoreState()


class GuideDoc(BaseDocTemplate):
    def __init__(self, *a, **kw):
        super().__init__(*a, **kw)
        frame = Frame(MARGIN, 2.1 * cm, CONTENT_W, PAGE_H - MARGIN - 2.1 * cm, id="f", leftPadding=0, rightPadding=0, topPadding=0, bottomPadding=0)
        self.addPageTemplates([
            PageTemplate("cover", frames=[frame], onPage=on_cover),
            PageTemplate("divider", frames=[frame], onPage=on_divider),
            PageTemplate("normal", frames=[frame], onPage=on_page),
        ])
        self._gg_footer = ""
        self._gg_divider = ("", "")
        self._gg_fr_page = "?"
        self._gg_en_page = "?"

    def afterFlowable(self, flowable):
        # Flowables can carry markers that change the footer / divider titles / page refs.
        for attr in ("_gg_footer", "_gg_divider"):
            if hasattr(flowable, attr):
                setattr(self, attr, getattr(flowable, attr))
        if getattr(flowable, "_gg_mark", None):
            self._gg_pages[flowable._gg_mark] = self.page


class Marker(Spacer):
    def __init__(self, **attrs):
        super().__init__(1, 0.01)
        for k, v in attrs.items():
            setattr(self, k, v)


# ---------- content ----------

FR = dict(
    lang="fr",
    divider=("Partie 1 — Guide", "En français"),
    footer="CafePOS · Guide de la caisse",
    welcome=(
        "Bienvenue ! Ce guide explique, pas à pas, tout ce qu'il faut savoir pour utiliser la caisse CafePOS. "
        "Pas besoin d'être un expert : suivez les étapes numérotées et regardez les images. "
        "Si vous vous trompez, ce n'est pas grave : rien ne se casse."
    ),
    toc_title="Dans ce guide",
    toc=["Démarrer", "La caisse", "Le tableau de bord", "Enregistrer un achat", "Les recettes",
         "Le rapport mensuel", "Depuis le téléphone", "Sauvegardes", "En cas de problème"],
    s1=("Démarrer", "Chaque matin, en ouvrant le café.", [
        "Allumez l'ordinateur portable.",
        f"Sur le bureau, double-cliquez sur l'icône {b('Caisse CafePOS')}.",
        "Attendez quelques secondes : la caisse s'ouvre toute seule dans le navigateur internet.",
    ]),
    s1_tips=[
        f"{b('Rien ne se passe ?')} Attendez 30 secondes, puis double-cliquez encore sur l'icône. "
        "Toujours rien ? Redémarrez l'ordinateur et double-cliquez à nouveau.",
        f"{b('Important :')} l'ordinateur doit rester allumé (et ne pas se mettre en veille) "
        "tant que le café est ouvert. Branchez-le sur le secteur.",
    ],
    icon_label="Caisse CafePOS",
    icon_hint="double-clic sur l'icône",
    s2=("La caisse", "C'est le travail du caissier. C'est tout ce qu'il a besoin de connaître.", [
        f"Touchez une {b('catégorie')} en haut (par exemple {b('Boissons chaudes')}).",
        "Touchez un produit pour l'ajouter à la commande. Touchez-le encore pour en ajouter un de plus.",
        f"À droite, utilisez {b('+')} pour ajouter, {b('−')} pour enlever, et {b('×')} pour supprimer la ligne.",
        f"Vérifiez le {b('Total')} en bas à droite.",
        f"Touchez le grand bouton vert {b('Confirmer')} : la commande est enregistrée.",
    ]),
    s2_after=[
        f"Le bouton {b('Vider')} efface toute la commande en cours (rien n'est enregistré).",
        f"Les boutons {b('FR')} / {b('EN')} en haut à droite changent la langue (français ou anglais).",
    ],
    s2_caption="La caisse avec une commande en cours : 2 Express, 1 Cappucine, 1 Eau 0.5, 2 Croissants.",
    s3=("Le tableau de bord", "Réservé au gérant. Il est protégé par un code secret (le PIN).", [
        f"Dans le navigateur, allez à l'adresse {b('localhost:8000/admin')}.",
        f"Tapez votre {b('PIN')}, puis touchez {b('Se connecter')}.",
    ]),
    s3_pin_tip=f"Le PIN de départ est {b('1234')} (sauf si l'installateur vous en a donné un autre).",
    s3_first=("La première chose à faire : changer le PIN", [
        f"Touchez le rond avec votre initiale, en haut à droite.",
        f"Touchez {b('Changer le PIN')}.",
        f"Tapez le PIN actuel, puis deux fois le nouveau PIN. Touchez {b('Enregistrer')}.",
    ]),
    s3_first_tip="Choisissez un PIN facile à retenir pour vous, mais difficile à deviner. Ne le donnez pas au caissier.",
    s3_numbers_title="Les quatre chiffres de l'accueil",
    s3_numbers=[
        f"{b('Ventes du jour')} : l'argent encaissé aujourd'hui.",
        f"{b('Ventes du mois')} : l'argent encaissé depuis le 1er du mois.",
        f"{b('Bénéfice net estimé (mois)')} : ventes − achats − charges fixes (loyer, salaires, électricité…).",
        f"{b('Ingrédients en rupture')} : combien d'ingrédients sont épuisés. Regardez dans {b('Stock')} lesquels.",
    ],
    s3_negative=(
        f"{b('Bénéfice négatif en début de mois ? C’est normal.')} Le loyer et les salaires du mois entier sont comptés "
        "dès le 1er jour. Le chiffre remonte au fil des ventes du mois."
    ),
    s4=("Enregistrer un achat", "Chaque fois que vous achetez de la marchandise, notez-la ici. C'est comme ça que le bénéfice reste juste.", [
        f"Dans le menu à gauche, touchez {b('Achats')}, puis le bouton {b('Créer')}.",
        f"{b('Ingrédient')} : choisissez ce que vous avez acheté (Café, Lait, Eau…).",
        f"{b('Nombre de conditionnements')} : combien de paquets, cartons ou packs.",
        f"{b('Coût total')} : le prix payé, en dinars.",
        f"{b('Date d’achat')} : la date du jour est déjà remplie.",
        f"Touchez {b('Créer')}. Le stock est mis à jour tout seul.",
    ]),
    s4_tip=f"Exemple : 1 kg de café à 25 DT → Ingrédient {b('Café')}, 1 conditionnement, coût total {b('25')}.",
    s5=("Les recettes", "Chaque produit a une recette : ce qu'il faut pour le préparer. La caisse s'en sert pour calculer le coût.", [
        f"Dans le menu à gauche, touchez {b('Produits')}.",
        f"Touchez {b('Modifier')} sur la ligne du produit (par exemple {b('Cappucine')}).",
        f"Descendez jusqu'au cadre {b('Recette')}.",
    ]),
    s5_unknown=(
        f"Pour certains produits ({b('Cappucine')}, {b('Direct')}, {b('Chocolat au Lait')}), la quantité de lait "
        f"est marquée {b('inconnu')}. Ce n'est pas une erreur : elle n'a pas encore été mesurée."
    ),
    s5_fix_title="Quand vous avez mesuré le lait",
    s5_fix=[
        f"Sur la ligne {b('Lait')}, touchez {b('Modifier')}.",
        f"Dans {b('Quantité')}, tapez les millilitres (ml) de lait d'une tasse, par exemple {b('60')}.",
        f"Touchez {b('Sauvegarder les modifications')}.",
    ],
    s5_tip="Une fois toutes les quantités remplies, les rapports deviennent exacts au lieu d'« estimés (~) ».",
    s6=("Le rapport mensuel", "Pour savoir si le mois a été bon.", [
        f"Dans le menu à gauche, touchez {b('Rapport mensuel')}.",
        f"En haut, choisissez le {b('Mois')}.",
        f"Lisez le {b('Résumé')} : ventes − achats du mois − charges fixes = {b('Bénéfice net')}.",
    ]),
    s6_caption1="Le résumé du mois : ici, un bénéfice net de 876 DT.",
    s6_table=f"Plus bas, {b('Ventes par produit')} montre ce qui se vend le mieux, et la marge de chaque produit.",
    s6_tip=(
        f"Le petit signe {b('~')} veut dire « estimé » : la recette de ce produit est incomplète. "
        f"Remplissez les quantités inconnues (voir {b('Les recettes')}) et le signe disparaît."
    ),
    s7=("Depuis le téléphone", "Le gérant peut voir le tableau de bord sur son téléphone, dans le café.", [
        f"Connectez le téléphone au {b('Wi-Fi du café')}.",
        f"Ouvrez le navigateur et tapez l'adresse donnée à l'installation. Elle ressemble à {b('http://192.168.x.x:8000/admin')}.",
        "Tapez votre PIN. Ajoutez la page aux favoris pour la prochaine fois.",
    ]),
    s7_addr="Mon adresse : http://192.168. ______ . ______ :8000/admin",
    s7_tips=[
        "Cela marche seulement si l'ordinateur est allumé et que la caisse a été démarrée (icône Caisse CafePOS).",
        f"{b('L’adresse ne marche plus ?')} La box internet l'a peut-être changée. Demandez à la personne qui a installé la caisse.",
        f"{b('Pour la personne qui aide :')} sur l'ordinateur, ouvrir l'invite de commandes, taper {b('ipconfig')} et lire la ligne « Adresse IPv4 ».",
    ],
    s8=("Sauvegardes", "Vos données sont copiées automatiquement. Vous n'avez presque rien à faire.", [
        f"Chaque nuit à {b('23h30')}, une copie est faite toute seule, si l'ordinateur est allumé.",
        f"Les copies sont rangées dans le dossier {b('storage\\backups')} de la caisse.",
        f"{b('Une fois par mois')} : copiez le fichier le plus récent de ce dossier sur une clé USB, et gardez la clé à la maison.",
    ]),
    s8_tip="Pourquoi la clé USB ? Si l'ordinateur tombe en panne ou est volé, vos données restent en sécurité.",
    s9=("En cas de problème", "Pas de panique. Dans presque tous les cas, ces deux gestes suffisent.", [
        "Redémarrez l'ordinateur.",
        f"Double-cliquez à nouveau sur l'icône {b('Caisse CafePOS')}.",
    ]),
    s9_tip=f"{b('Rien n’est perdu.')} Toutes les commandes sont enregistrées sur l'ordinateur, même après un redémarrage.",
    s9_contact="Toujours un souci ? Appelez la personne qui a installé la caisse :",
    contact_lines=["Nom : ______________________________", "Téléphone : _________________________"],
)

EN = dict(
    lang="en",
    divider=("Part 2 — Guide", "In English"),
    footer="CafePOS · Till guide",
    welcome=(
        "Welcome! This guide explains, step by step, everything you need to use the CafePOS till. "
        "You don't need to be an expert: follow the numbered steps and look at the pictures. "
        "If you make a mistake, don't worry: nothing breaks."
    ),
    toc_title="In this guide",
    toc=["Getting started", "The till", "The dashboard", "Recording a purchase", "Recipes",
         "The monthly report", "From the phone", "Backups", "If something goes wrong"],
    s1=("Getting started", "Every morning, when you open the cafe.", [
        "Switch on the laptop.",
        f"On the desktop, double-click the {b('Caisse CafePOS')} icon.",
        "Wait a few seconds: the till opens by itself in the web browser.",
    ]),
    s1_tips=[
        f"{b('Nothing happens?')} Wait 30 seconds, then double-click the icon again. "
        "Still nothing? Restart the laptop and double-click the icon again.",
        f"{b('Important:')} the laptop must stay on (and not go to sleep) "
        "while the cafe is open. Keep it plugged in.",
    ],
    icon_label="Caisse CafePOS",
    icon_hint="double-click the icon",
    s2=("The till", "This is the cashier's job. It is all the cashier needs to know.", [
        f"Tap a {b('category')} at the top (for example {b('Boissons chaudes')}, the hot drinks).",
        "Tap a product to add it to the order. Tap it again to add one more.",
        f"On the right, use {b('+')} to add, {b('−')} to remove one, and {b('×')} to delete the line.",
        f"Check the {b('Total')} at the bottom right.",
        f"Tap the big green {b('Confirm')} button: the order is recorded.",
    ]),
    s2_after=[
        f"The {b('Clear')} button empties the current order (nothing is recorded).",
        f"The {b('FR')} / {b('EN')} buttons at the top right switch the language (French or English).",
    ],
    s2_caption="The till with an order in progress: 2 Express, 1 Cappucine, 1 Eau 0.5, 2 Croissants.",
    s3=("The dashboard", "For the owner only. It is protected by a secret code (the PIN).", [
        f"In the browser, go to the address {b('localhost:8000/admin')}.",
        f"Type your {b('PIN')}, then tap {b('Sign in')}.",
    ]),
    s3_pin_tip=f"The starting PIN is {b('1234')} (unless the installer gave you a different one).",
    s3_first=("First thing to do: change the PIN", [
        "Tap the circle with your initial, at the top right.",
        f"Tap {b('Change PIN')}.",
        f"Type the current PIN, then the new PIN twice. Tap {b('Save')}.",
    ]),
    s3_first_tip="Pick a PIN that is easy for you to remember but hard to guess. Do not give it to the cashier.",
    s3_numbers_title="The four numbers on the home screen",
    s3_numbers=[
        f"{b('Sales today')}: the money taken today.",
        f"{b('Sales this month')}: the money taken since the 1st of the month.",
        f"{b('Estimated net profit (month)')}: sales − purchases − fixed expenses (rent, salaries, electricity…).",
        f"{b('Out-of-stock ingredients')}: how many ingredients have run out. Look in {b('Stock')} to see which.",
    ],
    s3_negative=(
        f"{b('Negative profit at the start of the month? That is normal.')} The rent and salaries for the whole month "
        "are counted from day 1. The number goes up as the month's sales come in."
    ),
    s4=("Recording a purchase", "Every time you buy goods, enter it here. That is how the profit stays true.", [
        f"In the menu on the left, tap {b('Purchases')}, then the {b('New purchase')} button.",
        f"{b('Ingredient')}: choose what you bought (Café, Lait, Eau…).",
        f"{b('Number of packages')}: how many packs, cartons or boxes.",
        f"{b('Total cost')}: the price you paid, in dinars.",
        f"{b('Purchase date')}: today's date is already filled in.",
        f"Tap {b('Create')}. The stock is updated by itself.",
    ]),
    s4_tip=f"Example: 1 kg of coffee for 25 DT → Ingredient {b('Café')}, 1 package, total cost {b('25')}.",
    s5=("Recipes", "Each product has a recipe: what goes into it. The till uses it to work out the cost.", [
        f"In the menu on the left, tap {b('Products')}.",
        f"Tap {b('Edit')} on the product's line (for example {b('Cappucine')}).",
        f"Scroll down to the {b('Recipe')} box.",
    ]),
    s5_unknown=(
        f"For some products ({b('Cappucine')}, {b('Direct')}, {b('Chocolat au Lait')}), the milk quantity "
        f"says {b('unknown')}. This is not a mistake: it simply has not been measured yet."
    ),
    s5_fix_title="When you have measured the milk",
    s5_fix=[
        f"On the {b('Lait')} line, tap {b('Edit')}.",
        f"In {b('Quantity')}, type the millilitres (ml) of milk in one cup, for example {b('60')}.",
        f"Tap {b('Save changes')}.",
    ],
    s5_tip="Once all the quantities are filled in, the reports become exact instead of \"estimated (~)\".",
    s6=("The monthly report", "To see whether the month was good.", [
        f"In the menu on the left, tap {b('Monthly report')}.",
        f"At the top, choose the {b('Month')}.",
        f"Read the {b('Summary')}: sales − purchases this month − fixed expenses = {b('Net profit')}.",
    ]),
    s6_caption1="The month summary: here, a net profit of 876 DT.",
    s6_table=f"Further down, {b('Sales by product')} shows what sells best, and the margin on each product.",
    s6_tip=(
        f"The small {b('~')} sign means \"estimated\": that product's recipe is incomplete. "
        f"Fill in the unknown quantities (see {b('Recipes')}) and the sign goes away."
    ),
    s7=("From the phone", "The owner can see the dashboard on his phone, inside the cafe.", [
        f"Connect the phone to the {b('cafe Wi-Fi')}.",
        f"Open the browser and type the address given at installation. It looks like {b('http://192.168.x.x:8000/admin')}.",
        "Type your PIN. Add the page to your bookmarks for next time.",
    ]),
    s7_addr="My address: http://192.168. ______ . ______ :8000/admin",
    s7_tips=[
        "It only works when the laptop is on and the till has been started (Caisse CafePOS icon).",
        f"{b('The address stopped working?')} The internet router may have changed it. Ask the person who installed the till.",
        f"{b('For the helper:')} on the laptop, open Command Prompt, type {b('ipconfig')} and read the \"IPv4 Address\" line.",
    ],
    s8=("Backups", "Your data is copied automatically. There is almost nothing to do.", [
        f"Every night at {b('23:30')}, a copy is made by itself, if the laptop is on.",
        f"The copies are kept in the till's {b('storage\\backups')} folder.",
        f"{b('Once a month')}: copy the newest file from that folder to a USB stick, and keep the stick at home.",
    ]),
    s8_tip="Why the USB stick? If the laptop breaks or is stolen, your data stays safe.",
    s9=("If something goes wrong", "Don't panic. In almost every case, these two steps are enough.", [
        "Restart the laptop.",
        f"Double-click the {b('Caisse CafePOS')} icon again.",
    ]),
    s9_tip=f"{b('Nothing is lost.')} All orders are saved on the laptop, even after a restart.",
    s9_contact="Still a problem? Call the person who installed the till:",
    contact_lines=["Name: ______________________________", "Phone: ______________________________"],
)


def desktop_drawing(label, hint):
    from reportlab.graphics.shapes import Drawing, Rect, String, Line, Circle
    w, h = CONTENT_W, 4.2 * cm
    d = Drawing(w, h)
    d.add(Rect(0, 0, w, h, fillColor=HexColor("#3c6e91"), strokeColor=None, rx=8, ry=8))
    # monitor-style icon
    ix, iy = 1.4 * cm, 1.5 * cm
    d.add(Rect(ix, iy, 1.6 * cm, 1.15 * cm, fillColor=HexColor("#dfe9f2"), strokeColor=white, strokeWidth=2, rx=3, ry=3))
    d.add(Rect(ix + 0.55 * cm, iy - 0.3 * cm, 0.5 * cm, 0.3 * cm, fillColor=white, strokeColor=None))
    d.add(String(ix + 0.8 * cm, iy - 0.85 * cm, label, fontName="Body-Semi", fontSize=11, fillColor=white, textAnchor="middle"))
    # double-click hint
    d.add(Circle(ix + 1.9 * cm, iy + 0.2 * cm, 9, fillColor=COPPER, strokeColor=white, strokeWidth=1.5))
    d.add(String(ix + 1.9 * cm, iy + 0.2 * cm - 4, "2×", fontName="Body-Bold", fontSize=10, fillColor=white, textAnchor="middle"))
    d.add(String(ix + 3.4 * cm, iy + 0.35 * cm, hint, fontName="Body", fontSize=12, fillColor=white))
    d.add(Line(ix + 2.3 * cm, iy + 0.45 * cm, ix + 3.2 * cm, iy + 0.45 * cm, strokeColor=white, strokeWidth=1.5))
    return d


def guide(T):
    L = T["lang"]
    st = []
    st += [Marker(_gg_footer=T["footer"]), NextPageTemplate("normal"), PageBreak()]

    # welcome + table of contents
    st.append(Paragraph(T["welcome"], S["intro"]))
    st.append(Spacer(1, 6))
    st.append(Paragraph(f"<b>{T['toc_title']}</b>", ParagraphStyle("tt", parent=S["body"], fontSize=14, textColor=COPPER)))
    for i, name in enumerate(T["toc"], 1):
        st.append(Paragraph(f"<font color='#8f4a1d'><b>{i}.</b></font>&nbsp;&nbsp;{name}", S["toc"]))
    st.append(Spacer(1, 14))

    # 1 getting started
    title, intro, items = T["s1"]
    st += heading(1, title, intro)
    st += steps(items)
    st += [desktop_drawing(T["icon_label"], T["icon_hint"]), Spacer(1, 12)]
    for t_ in T["s1_tips"]:
        st += tip(t_)

    # 2 the till
    st.append(PageBreak())
    title, intro, items = T["s2"]
    st += heading(2, title, intro)
    st += steps(items)
    st += shot("pos-order", L, T["s2_caption"])
    for t_ in T["s2_after"]:
        st += tip(t_)

    # 3 dashboard
    st.append(PageBreak())
    title, intro, items = T["s3"]
    st += heading(3, title, intro)
    st += steps(items)
    st += shot("login", L, width=8.5 * cm)
    st += tip(T["s3_pin_tip"])
    st.append(CondPageBreak(8 * cm))
    sub_title, sub_items = T["s3_first"]
    st.append(Paragraph(f"<b>{sub_title}</b>", ParagraphStyle("sub", parent=S["body"], fontSize=14, leading=19, textColor=COPPER, spaceBefore=6)))
    st += steps(sub_items)
    st += shot("usermenu", L, width=6.5 * cm)
    st += shot("changepin", L, width=13 * cm)
    st += tip(T["s3_first_tip"])
    st.append(CondPageBreak(10 * cm))
    st.append(Paragraph(f"<b>{T['s3_numbers_title']}</b>", ParagraphStyle("sub2", parent=S["body"], fontSize=14, leading=19, textColor=COPPER, spaceBefore=6)))
    st += shot("dashboard", L)
    for line in T["s3_numbers"]:
        st.append(Paragraph("•&nbsp;&nbsp;" + line, ParagraphStyle("bul", parent=S["body"], leftIndent=14, firstLineIndent=-10)))
    st.append(Spacer(1, 6))
    st += tip(T["s3_negative"])

    # 4 purchases
    st.append(PageBreak())
    title, intro, items = T["s4"]
    st += heading(4, title, intro)
    st += steps(items)
    st += shot("purchase-form", L)
    st += tip(T["s4_tip"])

    # 5 recipes
    st.append(PageBreak())
    title, intro, items = T["s5"]
    st += heading(5, title, intro)
    st += steps(items)
    st += shot("products", L, width=13 * cm)
    st += shot("recipe", L, width=14.5 * cm)
    st.append(p(T["s5_unknown"]))
    st.append(CondPageBreak(9 * cm))
    st.append(Paragraph(f"<b>{T['s5_fix_title']}</b>", ParagraphStyle("sub3", parent=S["body"], fontSize=14, leading=19, textColor=COPPER, spaceBefore=6)))
    st += steps(T["s5_fix"])
    st += shot("recipe-edit", L, width=14.5 * cm)
    st += tip(T["s5_tip"], color=HexColor("#e7f2ec"), bar=GREEN)

    # 6 report
    st.append(PageBreak())
    title, intro, items = T["s6"]
    st += heading(6, title, intro)
    st += steps(items)
    st += shot("report-summary", L, T["s6_caption1"])
    st.append(p(T["s6_table"]))
    st += tip(T["s6_tip"])
    st += shot("report-table", L)

    # 7 phone
    st.append(PageBreak())
    title, intro, items = T["s7"]
    st += heading(7, title, intro)
    st += steps(items)
    addr = Table([[Paragraph(f"<b>{T['s7_addr']}</b>", ParagraphStyle("addr", parent=S["body"], fontSize=13, leading=18))]], colWidths=[CONTENT_W])
    addr.setStyle(TableStyle([
        ("BOX", (0, 0), (-1, -1), 1.2, COPPER), ("LEFTPADDING", (0, 0), (-1, -1), 12),
        ("TOPPADDING", (0, 0), (-1, -1), 12), ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
    ]))
    st += [addr, Spacer(1, 12)]
    for t_ in T["s7_tips"]:
        st += tip(t_)

    # 8 backups
    title, intro, items = T["s8"]
    st += heading(8, title, intro)
    st += steps(items)
    st += tip(T["s8_tip"])

    # 9 problems
    title, intro, items = T["s9"]
    st += heading(9, title, intro)
    st += steps(items)
    st += tip(T["s9_tip"], color=HexColor("#e7f2ec"), bar=GREEN)
    st.append(p(T["s9_contact"]))
    for line in T["contact_lines"]:
        st.append(Paragraph(line, ParagraphStyle("contact", parent=S["body"], fontSize=13, leading=26)))
    return st


def build_story():
    story = []
    for T in (FR, EN):
        story += [Marker(_gg_divider=T["divider"], _gg_mark=T["lang"]), NextPageTemplate("divider"), PageBreak()]
        story += guide(T)
    return story


def build(fr_page, en_page):
    doc = GuideDoc(str(OUT), pagesize=A4, leftMargin=MARGIN, rightMargin=MARGIN, topMargin=MARGIN, bottomMargin=2.1 * cm,
                   title="CafePOS — Guide de la caisse / Till guide", author="CafePOS",
                   subject="Guide bilingue (français / English) pour le gérant et le caissier")
    doc._gg_fr_page, doc._gg_en_page = fr_page, en_page
    doc._gg_pages = {}
    doc.build(build_story())
    return doc._gg_pages


if __name__ == "__main__":
    # Two passes: the first finds where each part starts, the second prints it on the cover.
    pages = build("?", "?")
    pages = build(pages["fr"] + 1, pages["en"] + 1)
    print(f"Wrote {OUT} (Partie 1 p.{pages['fr'] + 1}, Part 2 p.{pages['en'] + 1})")
