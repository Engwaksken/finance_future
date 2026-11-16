/**
 * Ready-made sections offered in the Templates library ("Blocks" tab).
 * Ids are placeholders; the editor assigns fresh ids on insert.
 */
const w = (type, settings = {}) => ({ id: 'x', type, settings });
const col = (widgets, settings = {}) => ({ id: 'x', settings, widgets });
const section = (columns, settings = {}) => ({ id: 'x', settings, columns });

export const BLOCKS = [
    {
        name: 'Hero banner',
        icon: 'fa-flag',
        sections: [section([col([
            w('heading', { text: 'The future of money is being written today', tag: 'h1', align: { desktop: 'center' }, color: '#ffffff', show_in_toc: false }),
            w('text', { content: '<p>Clear, independent analysis on finance, fintech and the technologies reshaping markets.</p>', align: { desktop: 'center' }, color: '#e6edf3', font_size: { desktop: 20 } }),
            w('button', { text: 'Start reading', link: '#', align: { desktop: 'center' }, size: 'lg', bg: '#b99146', color: '#101820', icon: 'fa-solid fa-arrow-right' }),
        ])], {
            layout: 'full', bg_type: 'classic', bg_color: '#0b3b60', min_height: { desktop: 420 }, valign: 'center',
            _padding: { desktop: { top: 80, bottom: 80 }, mobile: { top: 48, bottom: 48 } },
        })],
    },
    {
        name: 'Three key points',
        icon: 'fa-table-columns',
        sections: [section([
            col([w('icon_box', { icon: 'fa-solid fa-chart-line', title: 'Markets', description: 'What moved this week and why it matters.', align: { desktop: 'center' } })]),
            col([w('icon_box', { icon: 'fa-solid fa-microchip', title: 'Technology', description: 'The tools and platforms changing finance.', align: { desktop: 'center' } })]),
            col([w('icon_box', { icon: 'fa-solid fa-shield-halved', title: 'Risk', description: 'What to watch before you invest.', align: { desktop: 'center' } })]),
        ], { gap: { desktop: 30 }, _padding: { desktop: { top: 40, bottom: 40 } } })],
    },
    {
        name: 'Key statistics',
        icon: 'fa-arrow-up-9-1',
        sections: [section([
            col([w('counter', { end: 72, suffix: '%', title: 'Adults with a mobile wallet', align: { desktop: 'center' } })]),
            col([w('counter', { end: 2.4, decimals: 1, prefix: '$', suffix: 'T', title: 'Daily digital payments', align: { desktop: 'center' } })]),
            col([w('counter', { end: 150, suffix: '+', title: 'Countries covered', align: { desktop: 'center' } })]),
        ], { bg_type: 'classic', bg_color: '#f7f8fa', border_radius: { desktop: 16 }, _padding: { desktop: { top: 36, right: 20, bottom: 36, left: 20 } } })],
    },
    {
        name: 'Image and text',
        icon: 'fa-image',
        sections: [section([
            col([w('image', { src: '', alt: '', img_radius: 14 })], { width: { desktop: 45 } }),
            col([
                w('heading', { text: 'Why this matters', tag: 'h2' }),
                w('text', { content: '<p>Explain the context behind the story. Pair a strong image with two or three short paragraphs.</p>' }),
                w('icon_list', { items: [
                    { _id: 'x', text: 'Point one', icon: 'fa-solid fa-check' },
                    { _id: 'x', text: 'Point two', icon: 'fa-solid fa-check' },
                ] }),
            ], { width: { desktop: 55 }, valign: 'center' }),
        ], { gap: { desktop: 40 }, valign: 'center' })],
    },
    {
        name: 'Key takeaways',
        icon: 'fa-circle-exclamation',
        sections: [section([col([
            w('callout', { kind: 'note', title: 'Key takeaways', content: '<ul><li>First takeaway.</li><li>Second takeaway.</li><li>Third takeaway.</li></ul>' }),
        ])])],
    },
    {
        name: 'FAQ',
        icon: 'fa-circle-question',
        sections: [section([col([
            w('heading', { text: 'Frequently asked questions', tag: 'h2' }),
            w('accordion', { faq_schema: true, items: [
                { _id: 'x', title: 'What is this article about?', content: '<p>Answer the question clearly and briefly.</p>' },
                { _id: 'x', title: 'Who should read it?', content: '<p>Describe the intended reader.</p>' },
                { _id: 'x', title: 'Where can I learn more?', content: '<p>Link to related reading.</p>' },
            ] }),
        ])])],
    },
    {
        name: 'Pull quote',
        icon: 'fa-quote-left',
        sections: [section([col([
            w('quote', { text: 'Price is what you pay. Value is what you get.', cite: 'Warren Buffett', variant: 'quotation', align: { desktop: 'center' } }),
        ])], { _padding: { desktop: { top: 30, bottom: 30 } } })],
    },
    {
        name: 'Newsletter call to action',
        icon: 'fa-bullhorn',
        sections: [section([col([
            w('cta', { title: 'Get the weekly briefing', description: 'One email, every Friday. The finance and technology stories that matter.', button_text: 'Subscribe', link: '/#newsletter', layout: 'row' }),
        ])])],
    },
    {
        name: 'Share and author',
        icon: 'fa-share-nodes',
        sections: [section([
            col([w('testimonial', { content: 'Written by our editorial team. We cover markets, fintech and the future of money.', name: 'Editorial team', role: 'Finance & Future Technology' })], { width: { desktop: 60 } }),
            col([w('share', { align: { desktop: 'right' } })], { width: { desktop: 40 }, valign: 'center' }),
        ], { border_style: 'solid', border_width: 1, border_color: '#e4e7ec', border_radius: { desktop: 14 }, _padding: { desktop: { top: 24, right: 24, bottom: 24, left: 24 } } })],
    },
];

export const STRUCTURES = [
    [100], [50, 50], [33.33, 33.33, 33.34], [25, 25, 25, 25],
    [33.33, 66.67], [66.67, 33.33], [25, 75], [75, 25],
    [25, 50, 25], [20, 20, 20, 20, 20], [16.66, 16.66, 16.66, 16.66, 16.66, 16.7], [30, 70],
];
