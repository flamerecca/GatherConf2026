<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
@foreach ($pages as $page)
@foreach ([route($page), route($page.'.en')] as $url)
    <url>
        <loc>{{ $url }}</loc>
        <xhtml:link rel="alternate" hreflang="zh-Hant-TW" href="{{ route($page) }}"/>
        <xhtml:link rel="alternate" hreflang="en" href="{{ route($page.'.en') }}"/>
        <xhtml:link rel="alternate" hreflang="x-default" href="{{ route($page) }}"/>
    </url>
@endforeach
@endforeach
</urlset>
