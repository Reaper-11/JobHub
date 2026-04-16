<?php
if (!function_exists('jobhub_render_site_footer')) {
    function jobhub_render_site_footer(): void
    {
        static $stylesPrinted = false;

        if (!$stylesPrinted) {
            $stylesPrinted = true;
            ?>
            <style>
                .jobhub-site-footer {
                    width: 100%;
                    padding: 30px 18px 28px;
                    background: #050b1a;
                }
                .jobhub-site-footer__inner {
                    width: min(100%, 900px);
                    margin: 0 auto;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 10px;
                    text-align: center;
                }
                .jobhub-site-footer__brand {
                    display: inline-flex;
                    align-items: baseline;
                    justify-content: center;
                    font-family: "Segoe UI", "Trebuchet MS", sans-serif;
                    font-size: clamp(1.9rem, 3vw, 2.35rem);
                    font-weight: 900;
                    line-height: 1;
                    letter-spacing: -0.07em;
                    white-space: nowrap;
                    text-rendering: optimizeLegibility;
                }
                .jobhub-site-footer__brand-job {
                    color: #ffffff;
                }
                .jobhub-site-footer__brand-hub {
                    color: #ff9f1a;
                    margin-left: -0.06em;
                }
                .jobhub-site-footer__tagline {
                    margin: 0;
                    max-width: 40rem;
                    color: #ffffff;
                    font-size: clamp(0.92rem, 1.2vw, 1.02rem);
                    line-height: 1.5;
                    font-weight: 600;
                }
                .jobhub-site-footer__meta {
                    margin: 0;
                    color: #9aa9c4;
                    font-size: 0.82rem;
                    line-height: 1.5;
                }
                @media (max-width: 768px) {
                    .jobhub-site-footer {
                        padding: 26px 16px 24px;
                    }
                    .jobhub-site-footer__inner {
                        gap: 9px;
                    }
                    .jobhub-site-footer__brand {
                        font-size: 1.8rem;
                    }
                }
                @media (max-width: 480px) {
                    .jobhub-site-footer {
                        padding: 24px 14px 22px;
                    }
                    .jobhub-site-footer__brand {
                        font-size: 1.62rem;
                    }
                    .jobhub-site-footer__tagline {
                        font-size: 0.9rem;
                    }
                    .jobhub-site-footer__meta {
                        font-size: 0.8rem;
                    }
                }
            </style>
            <?php
        }
        ?>
        <footer class="jobhub-site-footer" role="contentinfo">
            <div class="jobhub-site-footer__inner">
                <div class="jobhub-site-footer__brand" aria-label="JobHub">
                    <span class="jobhub-site-footer__brand-job">Job</span><span class="jobhub-site-footer__brand-hub">Hub</span>
                </div>
                <p class="jobhub-site-footer__tagline">A Simple &amp; Practical Job Portal for Nepal</p>
                <p class="jobhub-site-footer__meta">&copy; 2026 JobHub. All rights reserved.</p>
            </div>
        </footer>
        <?php
    }
}
