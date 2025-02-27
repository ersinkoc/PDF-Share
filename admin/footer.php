            </main>
            
            <!-- Footer -->
            <footer class="bg-white p-4 shadow mt-8">
                <div class="container mx-auto">
                    <p class="text-center text-gray-500 text-sm">
                        <?php 
                        // Get footer text from settings, with fallback to default
                        $footerText = getSettingValue('general.footer_text', '&copy; ' . date('Y') . ' ' . getSettingValue('general.site_title', 'PDF QR Link') . '. All rights reserved.');
                        echo $footerText;
                        ?>
                    </p>
                </div>
            </footer>
        </div>
    </div>
</body>
</html>
