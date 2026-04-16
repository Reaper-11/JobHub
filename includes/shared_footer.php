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
                    padding: 45px 20px 40px;
                    background: linear-gradient(180deg, rgba(5, 11, 26, 0.8) 0%, #050b1a 100%);
                    border-top: 1px solid rgba(96, 165, 250, 0.15);
                    margin-top: auto;
                }
                .jobhub-site-footer__inner {
                    width: min(100%, 1200px);
                    margin: 0 auto;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    gap: 14px;
                    text-align: center;
                }
                .jobhub-site-footer__brand {
                    display: inline-flex;
                    align-items: baseline;
                    justify-content: center;
                    font-family: "Segoe UI", "Trebuchet MS", sans-serif;
                    font-size: clamp(1.8rem, 3.5vw, 2.3rem);
                    font-weight: 900;
                    line-height: 1;
                    letter-spacing: -0.09em;
                    white-space: nowrap;
                    text-rendering: optimizeLegibility;
                    margin-bottom: 5px;
                }
                .jobhub-site-footer__brand-job {
                    color: #ffffff;
                    font-weight: 900;
                    letter-spacing: -0.02em;
                }
                .jobhub-site-footer__brand-hub {
                    color: #ff9f1a !important;
                    margin-left: -0.12em;
                    font-weight: 900;
                    letter-spacing: -0.02em;
                }
                .jobhub-site-footer__tagline {
                    margin: 0;
                    max-width: 55rem;
                    color: #e2e8f0;
                    font-size: clamp(0.92rem, 1vw, 0.98rem);
                    line-height: 1.6;
                    font-weight: 500;
                    letter-spacing: 0.3px;
                }
                .jobhub-site-footer__meta {
                    margin: 0;
                    color: #94a3b8;
                    font-size: clamp(0.8rem, 0.9vw, 0.86rem);
                    line-height: 1.5;
                    font-weight: 400;
                    letter-spacing: 0.1px;
                }
                @media (max-width: 768px) {
                    .jobhub-site-footer {
                        padding: 38px 18px 32px;
                    }
                    .jobhub-site-footer__inner {
                        gap: 12px;
                    }
                    .jobhub-site-footer__brand {
                        font-size: 1.7rem;
                        margin-bottom: 4px;
                    }
                    .jobhub-site-footer__tagline {
                        font-size: 0.92rem;
                    }
                }
                @media (max-width: 480px) {
                    .jobhub-site-footer {
                        padding: 32px 16px 26px;
                    }
                    .jobhub-site-footer__inner {
                        gap: 10px;
                    }
                    .jobhub-site-footer__brand {
                        font-size: 1.5rem;
                        margin-bottom: 3px;
                    }
                    .jobhub-site-footer__tagline {
                        font-size: 0.88rem;
                    }
                    .jobhub-site-footer__meta {
                        font-size: 0.78rem;
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
