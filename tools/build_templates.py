"""
Generate the HomeFlip page designs as Elementor documents -> templates/*.json.

    python homeflip-connector/tools/build_templates.py

Why a generator and not hand-edited Elementor JSON: every page shares one set of
building blocks (section, heading, button...), so spacing, colors and fonts stay
consistent, and a change to a block changes every page. Bump a page's VERSION when
its content changes; the plugin refreshes the library copy on sites that have an
older one (includes/templates.php).

Rules every design follows:
- FREE Elementor widgets only (no Pro, no PowerPack): heading, text-editor, button,
  icon-list, icon-box, shortcode.
- Colors are Elementor GLOBAL colors (primary / secondary / text / accent), never
  hex, so a customer re-skins every page from Site Settings -> Global Colors.
- No business detail is ever typed in. Name, phone, city... come from
  [homeflip_business field="..."] (text-editor widgets only: Elementor Free does
  not run shortcodes in Heading or Button text).
- Forms by NAME: [homeflip_form name="..."], never a Forminator ID.
- No invented guarantees or testimonials. A customer's promise is theirs to make;
  placeholders are bracketed so they are obvious until replaced.
- Structure modeled on whitebox.properties (home 4929, seller hero 1702, how it
  works 1270, FAQ 1273), the pages that have converted for Gary.
"""

import hashlib
import json
import os

OUT = os.path.join(os.path.dirname(__file__), '..', 'templates')

_seq = 0


def _id(seed=''):
    """Stable 7-hex element id: rebuilding does not churn every id."""
    global _seq
    _seq += 1
    return hashlib.md5(f'{_page}:{_seq}:{seed}'.encode()).hexdigest()[:7]


_page = ''

G = lambda name: f'globals/colors?id={name}'  # noqa: E731
FONT = 'globals/typography?id='


def px(top, right=None, bottom=None, left=None):
    right = top if right is None else right
    bottom = top if bottom is None else bottom
    left = right if left is None else left
    return {'unit': 'px', 'top': str(top), 'right': str(right), 'bottom': str(bottom),
            'left': str(left), 'isLinked': False}


# --------------------------------------------------------------------------- blocks

def section(children, bg=None, dark=False, anchor='', pad=80, narrow=False, css=''):
    """Full-width band with a boxed column of content."""
    s = {
        'content_width': 'boxed',
        'flex_direction': 'column',
        'flex_align_items': 'center',
        'flex_gap': {'unit': 'px', 'size': 20, 'column': '20', 'row': '20'},
        'padding': px(pad, 20),
        'padding_mobile': px(max(40, pad // 2), 16),
    }
    if narrow:
        s['boxed_width'] = {'unit': 'px', 'size': 820}
    if bg:
        s['background_background'] = 'classic'
        s['__globals__'] = {'background_color': G(bg)}
    if anchor:
        s['_element_id'] = anchor
    if css:
        s['css_classes'] = css
    return {'id': _id('sec'), 'elType': 'container', 'isInner': False, 'settings': s,
            'elements': [_on_dark(c) if dark else c for c in children]}


def row(children, cols):
    """Side-by-side columns that stack on phones."""
    width = round(100 / cols, 2) - 2
    kids = []
    for c in children:
        kids.append({'id': _id('col'), 'elType': 'container', 'isInner': True,
                     'settings': {'content_width': 'full', 'flex_direction': 'column',
                                  'width': {'unit': '%', 'size': width},
                                  'width_mobile': {'unit': '%', 'size': 100},
                                  'padding': px(10)},
                     'elements': c if isinstance(c, list) else [c]})
    return {'id': _id('row'), 'elType': 'container', 'isInner': True,
            'settings': {'content_width': 'full', 'flex_direction': 'row',
                         'flex_wrap': 'wrap', 'flex_justify_content': 'center',
                         'flex_gap': {'unit': 'px', 'size': 20, 'column': '20', 'row': '20'}},
            'elements': kids}


def _on_dark(el):
    """Light text for elements placed on a primary-colored band."""
    st = el['settings']
    wt = el.get('widgetType')
    if wt == 'heading':
        st.pop('__globals__', None)
        st['title_color'] = '#FFFFFF'
    elif wt == 'text-editor':
        st.pop('__globals__', None)
        st['text_color'] = '#FFFFFF'
    elif wt == 'icon-list':
        st['text_color'] = '#FFFFFF'
    for child in el.get('elements', []):
        _on_dark(child)
    return el


def heading(text, size='h2', align='center', px_size=36):
    return {'id': _id('h'), 'elType': 'widget', 'widgetType': 'heading', 'elements': [],
            'settings': {'title': text, 'header_size': size, 'align': align,
                         'typography_typography': 'custom',
                         'typography_font_size': {'unit': 'px', 'size': px_size},
                         'typography_font_size_mobile': {'unit': 'px', 'size': max(24, round(px_size * .7))},
                         'typography_font_weight': '700',
                         'typography_line_height': {'unit': 'em', 'size': 1.2},
                         '__globals__': {'title_color': G('primary')}}}


def text(html, align='center', px_size=18):
    return {'id': _id('t'), 'elType': 'widget', 'widgetType': 'text-editor', 'elements': [],
            'settings': {'editor': html, 'align': align,
                         'typography_typography': 'custom',
                         'typography_font_size': {'unit': 'px', 'size': px_size},
                         'typography_line_height': {'unit': 'em', 'size': 1.6},
                         '__globals__': {'text_color': G('text')}}}


def button(label, url, align='center'):
    return {'id': _id('b'), 'elType': 'widget', 'widgetType': 'button', 'elements': [],
            'settings': {'text': label, 'link': {'url': url, 'is_external': '', 'nofollow': ''},
                         'align': align, 'size': 'lg', 'button_text_color': '#FFFFFF',
                         'border_radius': {'unit': 'px', 'top': '6', 'right': '6', 'bottom': '6',
                                           'left': '6', 'isLinked': True},
                         'typography_typography': 'custom', 'typography_font_weight': '700',
                         '__globals__': {'background_color': G('accent')}}}


def shortcode(code):
    return {'id': _id('s'), 'elType': 'widget', 'widgetType': 'shortcode', 'elements': [],
            'settings': {'shortcode': code}}


def checklist(items):
    return {'id': _id('l'), 'elType': 'widget', 'widgetType': 'icon-list', 'elements': [],
            'settings': {'icon_list': [{'_id': _id('li'), 'text': t,
                                        'selected_icon': {'value': 'fas fa-check-circle',
                                                          'library': 'fa-solid'}} for t in items],
                         'space_between': {'unit': 'px', 'size': 10},
                         'icon_size': {'unit': 'px', 'size': 20},
                         'text_typography_typography': 'custom',
                         'text_typography_font_size': {'unit': 'px', 'size': 19},
                         '__globals__': {'icon_color': G('accent')}}}


def step(icon, title, body):
    return {'id': _id('ib'), 'elType': 'widget', 'widgetType': 'icon-box', 'elements': [],
            'settings': {'selected_icon': {'value': icon, 'library': 'fa-solid'},
                         'title_text': title, 'description_text': body, 'position': 'top',
                         'title_size': 'h3', 'icon_size': {'unit': 'px', 'size': 40},
                         '__globals__': {'primary_color': G('accent'), 'title_color': G('primary'),
                                         'description_color': G('text')}}}



def faq(q, a):
    return [heading(q, 'h3', 'left', 21), text(f'<p>{a}</p>', 'left', 17)]


B = lambda f: f'[homeflip_business field="{f}"]'  # noqa: E731

SMS_NOTE = ('<p style="font-size:13px">By providing your phone number, you agree to receive text '
            f'messages from {B("name")}. Message and data rates may apply. Message frequency varies. '
            'Reply STOP to opt out. See our <a href="/privacy-policy/">Privacy Policy</a> and '
            '<a href="/terms/">Terms</a>.</p>')


def contact_band():
    return section([
        heading('Questions? Talk to a real person.', 'h2', 'center', 30),
        text('<p>[homeflip_contact]</p>'),
        shortcode('[homeflip_phone_button label="Call or text"]'),
        text(SMS_NOTE),
    ], bg='secondary', pad=60)


# --------------------------------------------------------------------------- pages

def page_home():
    return [
        section([
            text('<p style="letter-spacing:2px;font-weight:700;text-transform:uppercase">'
                 '[homeflip_business field="city" before="Sell your house fast in " '
                 'fallback="Sell your house fast"]</p>', px_size=15),
            heading('Get a fair cash offer for your house. No repairs, no agents, no hassle.',
                    'h1', 'center', 48),
            checklist([
                'A no-obligation cash offer, with no pressure to accept',
                'Sell as-is: no repairs, cleaning or updates',
                'No agent commissions',
                'Close on the date that works for you',
                "Leave behind anything you don't want",
            ]),
            button('Get My Cash Offer', '#offer'),
            shortcode('[homeflip_phone_button label="Or call"]'),
        ], bg='primary', dark=True, pad=110, css='homeflip-hero'),

        section([
            heading('How it works', 'h2'),
            row([
                step('fas fa-file-alt', '1. Tell us about the house',
                     'Fill out the short form below. It only takes a minute and it stays private.'),
                step('fas fa-phone', "2. We'll call you",
                     'We call you back quickly to learn about the house and your situation.'),
                step('fas fa-handshake', '3. Get your offer',
                     'We give you a fair cash offer. No obligation, and no pressure to accept.'),
                step('fas fa-key', '4. Close on your date',
                     "You pick the closing date and walk away with cash. It's that simple."),
            ], 4),
        ]),

        section([
            heading('Get your no-obligation offer', 'h2'),
            text("<p>Tell us about the house and we'll get back to you quickly.</p>"),
            shortcode('[homeflip_form name="seller"]'),
        ], bg='secondary', anchor='offer', narrow=True),


        section([
            heading('Frequently asked questions', 'h2'),
            row([
                faq('How much will you pay for my house?',
                    'Every house and situation is different. We look at the condition of the '
                    'house, the repairs it needs, and what similar homes nearby have sold for, '
                    'then make you a fair offer. You are never under any obligation to accept.'),
                faq('Will this cost me anything?',
                    'There are no agent commissions. We will walk you through every cost before '
                    'you sign anything, so there are no surprises at closing.'),
                faq('Do I need to make repairs first?',
                    'No. We buy houses as-is. You do not need to fix, clean or update anything.'),
                faq('How fast can you close?',
                    'We close on your timeline. If you need to move quickly, we can. If you need '
                    'more time, we will work around your schedule.'),
                faq('How do I get my money?',
                    'We close with a licensed local title company. They can wire your money to '
                    'you at closing or hand you a check, whichever you prefer.'),
                faq('What if I still owe money on the house?',
                    'That is common. The title company pays off your mortgage from the sale at '
                    'closing, and you receive what is left.'),
            ], 2),
        ], bg='secondary'),

        section([
            heading('Are you a real estate investor?', 'h2', 'center', 30),
            text('<p>Join our buyers list and get our off-market deals before anyone else.</p>'),
            button('Join Our Buyers List', '/buyers-list/'),
        ], pad=60),

        contact_band(),
    ]


def page_buyers_list():
    return [
        section([
            heading('Get off-market deals before anyone else', 'h1', 'center', 44),
            text('<p>Join our buyers list. When we have a property that fits what you buy, '
                 'you hear about it first.</p>'),
        ], bg='primary', dark=True, pad=90, css='homeflip-hero'),
        section([
            heading('Join our buyers list', 'h2', 'center', 30),
            shortcode('[homeflip_form name="buyer_profile"]'),
            text('<p>Already on the list? <a href="/buy-box/">Tell us exactly what you want to '
                 'buy next</a> and we will send you deals that match.</p>'),
        ], narrow=True),
        contact_band(),
    ]


def page_buy_box():
    return [
        section([
            heading('Tell us what you want to buy next', 'h1', 'center', 44),
            text('<p>Describe your next purchase: where, what price, what condition. We match '
                 'every deal we get against your buy box and send you the ones that fit.</p>'),
        ], bg='primary', dark=True, pad=90, css='homeflip-hero'),
        section([
            text('<p>Buying different things in different areas? Fill this out once for each '
                 'one. Use the same email every time.</p>', px_size=16),
            shortcode('[homeflip_form name="next_purchase"]'),
        ], narrow=True),
        contact_band(),
    ]


def legal(title, body_html):
    return [
        section([heading(title, 'h1', 'center', 40)], bg='secondary', pad=60),
        section([text(body_html, 'left', 17)], narrow=True, pad=60),
    ]


def page_privacy():
    n = B('name')
    return legal('Privacy Policy', f'''
<p>This Privacy Policy explains how {n} ("we," "us") collects and uses the information you
give us on this website.</p>
<h3>What we collect</h3>
<p>When you fill out a form on this site, we collect the information you enter, such as your
name, email address, phone number, property address, and details about the property or what
you want to buy. We also record the page you submitted the form from.</p>
<h3>How we use it</h3>
<p>We use your information to respond to you, to make you an offer on your property, to send
you properties that match what you told us you want to buy, and to keep you updated about our
services. If you opted in, we may contact you by text message and email.</p>
<h3>Text messages</h3>
<p>If you check the opt-in box, you agree to receive text messages from {n}. Message frequency
varies. Message and data rates may apply. Reply STOP at any time to stop receiving texts, or
HELP for help. Opting in is never a condition of doing business with us.</p>
<p><strong>We will not share or sell your mobile phone number or text messaging consent with
third parties or affiliates for marketing purposes.</strong></p>
<h3>Sharing</h3>
<p>We do not sell your personal information. We share it only with service providers who help
us run our business (for example, the software we use to store contacts and send messages),
and when required by law.</p>
<h3>Your choices</h3>
<p>You can ask us to update or delete your information, or unsubscribe from emails at any time
using the link in any email.</p>
<h3>Contact us</h3>
<p>[homeflip_contact address="yes"]</p>''')


def page_terms():
    n = B('name')
    return legal('Terms', f'''
<p>By using this website you agree to these terms. If you do not agree, please do not use the
site.</p>
<h3>No obligation</h3>
<p>Submitting a form does not obligate you to sell your property or to buy any property. Any
offer we make is subject to a written purchase agreement signed by both parties.</p>
<h3>Property information</h3>
<p>Property details, photos, prices and estimates on this site are provided for information
only and may change. Buyers are responsible for their own inspection and due diligence.</p>
<h3>Text message program</h3>
<p>{n} sends text messages about properties and our services to people who opted in on this
website. Message frequency varies. Message and data rates may apply. Reply STOP to cancel and
HELP for help. Carriers are not liable for delayed or undelivered messages. See our
<a href="/privacy-policy/">Privacy Policy</a>.</p>
<h3>Contact</h3>
<p>[homeflip_contact address="yes"]</p>''')


PAGES = [
    # slug, title, VERSION, starter page (title, path, flags) or None, builder
    ('home', 'Home: We Buy Houses', 3,
     {'title': 'Home', 'path': 'home', 'front_page': True}, page_home),
    ('buyers-list', 'Join Our Buyers List', 2,
     {'title': 'Join Our Buyers List', 'path': 'buyers-list'}, page_buyers_list),
    ('buy-box', 'Your Buy Box', 2,
     {'title': 'Your Buy Box', 'path': 'buy-box'}, page_buy_box),
    ('privacy-policy', 'Privacy Policy', 2,
     {'title': 'Privacy Policy', 'path': 'privacy-policy', 'privacy_page': True}, page_privacy),
    ('terms', 'Terms', 2,
     {'title': 'Terms', 'path': 'terms'}, page_terms),
]


def main():
    global _page, _seq
    os.makedirs(OUT, exist_ok=True)
    for slug, title, version, page, build in PAGES:
        _page, _seq = slug, 0
        doc = {'slug': slug, 'title': title, 'homeflip_version': version, 'type': 'page',
               'page': page, 'content': build()}
        path = os.path.join(OUT, f'{slug}.json')
        with open(path, 'w', encoding='utf-8', newline='\n') as f:
            json.dump(doc, f, indent=1, ensure_ascii=False)
            f.write('\n')
        print(f'{slug:16} v{version}  -> {os.path.relpath(path)}')


if __name__ == '__main__':
    main()
