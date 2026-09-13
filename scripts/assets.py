from pathlib import Path

root=Path(__file__).resolve().parents[1]/'public'/'products'
root.mkdir(parents=True,exist_ok=True)
defs='''<defs>
<linearGradient id="white" x1="0" x2="1"><stop stop-color="#d8dacc"/><stop offset=".18" stop-color="#fffef2"/><stop offset=".46" stop-color="#f4f3e7"/><stop offset=".72" stop-color="#d8ddce"/><stop offset=".9" stop-color="#faf9ed"/><stop offset="1" stop-color="#ced4c3"/></linearGradient>
<linearGradient id="green" x1="0" x2="1"><stop stop-color="#173f31"/><stop offset=".2" stop-color="#5c7750"/><stop offset=".48" stop-color="#354e32"/><stop offset=".8" stop-color="#59794b"/><stop offset="1" stop-color="#203e2b"/></linearGradient>
<linearGradient id="black" x1="0" x2="1"><stop stop-color="#28342c"/><stop offset=".25" stop-color="#657262"/><stop offset=".52" stop-color="#394738"/><stop offset=".8" stop-color="#596552"/><stop offset="1" stop-color="#202d25"/></linearGradient>
<linearGradient id="yellow" x1="0" x2="1"><stop stop-color="#b5a16c"/><stop offset=".3" stop-color="#e0d2a2"/><stop offset=".65" stop-color="#c4b77f"/><stop offset="1" stop-color="#a8955f"/></linearGradient>
<linearGradient id="blue" x1="0" x2="1"><stop stop-color="#8daaa4"/><stop offset=".3" stop-color="#c3d9d0"/><stop offset=".65" stop-color="#a0bfb5"/><stop offset="1" stop-color="#6c938a"/></linearGradient>
<linearGradient id="plastic" x1="0" x2="1"><stop stop-color="#ffffff" stop-opacity=".12"/><stop offset=".15" stop-color="#ffffff" stop-opacity=".72"/><stop offset=".45" stop-color="#dee5d5" stop-opacity=".45"/><stop offset=".76" stop-color="#ffffff" stop-opacity=".85"/><stop offset="1" stop-color="#c4cfba" stop-opacity=".6"/></linearGradient>
<radialGradient id="ground"><stop stop-color="#34482b" stop-opacity=".22"/><stop offset="1" stop-color="#34482b" stop-opacity="0"/></radialGradient>
<filter id="shadow" x="-40%" y="-40%" width="180%" height="180%"><feDropShadow dx="5" dy="12" stdDeviation="9" flood-color="#253e22" flood-opacity=".19"/></filter>
</defs>'''

def roll(x,y,w,h,color='white',label=False,angle=0):
    out=f'<g transform="translate({x} {y}) rotate({angle} {w/2} {h/2})" filter="url(#shadow)"><path d="M0 13 Q{w/2} -12 {w} 13 V{h-12} Q{w/2} {h+10} 0 {h-12}Z" fill="url(#{color})"/><ellipse cx="{w/2}" cy="13" rx="{w/2}" ry="13" fill="#d0d6c0"/><ellipse cx="{w/2}" cy="13" rx="{w*.39}" ry="9" fill="none" stroke="#a7b29a" stroke-width="1.2"/><ellipse cx="{w/2}" cy="13" rx="{w*.27}" ry="6" fill="none" stroke="#bbc3a9"/><ellipse cx="{w/2}" cy="13" rx="{w*.1}" ry="4" fill="#727964"/>'
    for n in range(1,8):
        a=w*n/8
        out+=f'<path d="M{a} 28 Q{a-3} {h/2} {a+1} {h-14}" fill="none" stroke="#ffffff" opacity=".13"/>'
    if label:
        out+=f'<path d="M0 {h*.42} Q{w/2} {h*.48} {w} {h*.42} V{h*.77} Q{w/2} {h*.83} 0 {h*.77}Z" fill="#e9e7ce"/><path d="M0 {h*.43} Q{w/2} {h*.49} {w} {h*.43} V{h*.48} Q{w/2} {h*.54} 0 {h*.48}Z" fill="#3e6042"/><text x="{w/2}" y="{h*.6}" text-anchor="middle" font-family="Arial" font-size="{w*.115}" fill="#365540" font-weight="bold">SADAT</text><text x="{w/2}" y="{h*.67}" text-anchor="middle" font-family="Arial" font-size="{w*.06}" fill="#718061" letter-spacing="2">PLAST</text><path d="M{w*.3} {h*.73}h{w*.4}" stroke="#92a07e"/>'
    return out+'</g>'

def bag(x,y,w,h,color='plastic',angle=0):
    return f'''<g transform="translate({x} {y}) rotate({angle} {w/2} {h/2})" filter="url(#shadow)"><path d="M{w*.06} {h*.95} L{w*.1} {h*.32} L{w*.16} 3 Q{w*.25} -5 {w*.34} 3 L{w*.36} {h*.27} Q{w*.5} {h*.35} {w*.64} {h*.27} L{w*.66} 3 Q{w*.75} -5 {w*.84} 3 L{w*.9} {h*.32} L{w*.95} {h*.95} Q{w*.5} {h*1.02} {w*.06} {h*.95}Z" fill="url(#{color})" stroke="#bdc9b2" stroke-opacity=".6"/><path d="M{w*.16} {h*.13} L{w*.2} {h*.93} M{w*.84} {h*.14} L{w*.8} {h*.94} M{w*.35} {h*.38} L{w*.3} {h*.89} M{w*.65} {h*.38} L{w*.7} {h*.91} M{w*.12} {h*.91} Q{w*.55} {h*.97} {w*.9} {h*.92}" stroke="#fff" stroke-opacity=".57" fill="none" stroke-width="2"/><path d="M{w*.14} {h*.85} L{w*.33} {h*.67} M{w*.84} {h*.78} L{w*.68} {h*.61}" stroke="#aab9a0" stroke-opacity=".35" fill="none"/></g>'''

def pack(x,y,w,h,color='white'):
    return f'''<g transform="translate({x} {y}) rotate(-8)" filter="url(#shadow)"><path d="M0 12L{w-15} 0L{w} {h-12}L10 {h}Z" fill="url(#{color})" stroke="#c1cbb5"/><path d="M8 20L{w-8} 8M9 25L{w-7} 13M12 {h-8}L{w-3} {h-20}" stroke="#fff" opacity=".6"/><path d="M5 {h*.3}L{w-10} {h*.3-12}L{w-4} {h*.68}L8 {h*.68+12}Z" fill="#32604b"/><text x="{w/2}" y="{h*.47}" text-anchor="middle" font-family="Arial" font-size="16" fill="#f8f4df" font-weight="bold">SADAT NYLEX</text><text x="{w/2}" y="{h*.57}" text-anchor="middle" font-family="Arial" font-size="8" fill="#b8c8a6" letter-spacing="2">EVERYDAY QUALITY</text></g>'''

def svg(content,w=400,h=320):
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}">{defs}{content}</svg>'

ground='<ellipse cx="205" cy="269" rx="151" ry="31" fill="url(#ground)"/>'
arts=[roll(118,55,82,205,'white',True,-12)+roll(201,98,70,171,'white',True,12),roll(113,65,94,203,'black',True,-15)+roll(211,99,66,163,'green',True,15),bag(105,35,183,245,'white',-9),roll(135,42,113,226,'plastic',False,12),pack(93,75,210,180,'blue'),roll(103,61,104,218,'black',True,-10)+roll(218,110,67,159,'black',True,17),roll(130,45,95,232,'yellow',True,-20)+roll(222,128,60,145,'white',False,23),bag(84,54,160,220,'blue',-14)+bag(184,47,145,225,'yellow',12)]
for i,art in enumerate(arts,1):
    (root/f'product-{i}.svg').write_text(svg(ground+art),encoding='utf-8')
hero='<ellipse cx="333" cy="439" rx="263" ry="48" fill="url(#ground)"/>'
hero+=bag(120,82,206,300,'plastic',-13)
hero+=bag(275,40,226,335,'white',7)
hero+=roll(405,175,105,243,'black',True,15)
hero+=roll(229,189,108,236,'white',True,-12)
hero+=roll(334,244,94,198,'green',True,10)
hero+=roll(102,297,73,167,'white',False,-72)
hero+=pack(171,370,171,94,'white')
(root/'hero.svg').write_text(svg(hero,650,520),encoding='utf-8')
print('Created 8 product illustrations and hero artwork.')
