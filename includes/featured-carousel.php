<!-- Featured Events Carousel (is_general_featured) -->
<section id="featuredCarouselSection" class="hidden py-10 overflow-hidden md:py-14 bg-gradient-to-b from-gray-50 to-white">
    <div class="px-4 mx-auto mb-8 max-w-7xl">
        <div class="flex items-center gap-3">
            <span class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary/10">
                <svg class="w-5 h-5 text-primary" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
            </span>
            <h2 class="text-2xl font-bold text-secondary">Evenimente Recomandate</h2>
        </div>
    </div>
    <!-- Full width carousel container -->
    <div class="relative w-full">
        <div id="featuredCarouselTrack" class="flex gap-6 featured-carousel-track">
            <!-- Events loaded dynamically and duplicated for infinite scroll -->
        </div>
    </div>
</section>

<style>
.featured-carousel-track {
    animation: scrollCarousel 30s linear infinite;
    width: max-content;
}

.featured-carousel-track:hover {
    animation-play-state: paused;
}

@keyframes scrollCarousel {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-50%);
    }
}

.featured-carousel-card {
    flex-shrink: 0;
    width: 320px;
}

@media (min-width: 768px) {
    .featured-carousel-card {
        width: 380px;
    }
}
</style>
