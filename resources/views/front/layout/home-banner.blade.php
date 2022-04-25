<!--================ Start Home Banner Area =================-->
<section class="home_banner_area">
  <div class="banner_inner">
    <div class="container">
      <div class="row flex-wrap align-content-center">
        <div class="col-lg-12">
          <div class="banner_content text-center mt-5 py-5">
            <p class="text-uppercase bg-overlay-primary p-3" style="width: auto; display: inline-block; background-color: rgba(255, 200, 50, 0.8)">
              <strong>{{ strtoupper(config('app.name')) }}</strong>
            </p>
            <br>
            <h3 class="text-uppercase bg-overlay-secondary mb-4 p-3 text-white" style="width: auto; display: inline-block; margin-top: -100px; background-color: rgba(20, 40, 120, 0.8);">
              Thrifting Bekasi
            </h3>
            <div>
              <a href="{{ route('front::products.index') }}" class="btn btn-primary btn-lg mb-3 mb-sm-0">BROWSE PRODUCTS</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<!--================ End Home Banner Area =================-->
